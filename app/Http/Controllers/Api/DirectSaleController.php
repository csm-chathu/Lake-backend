<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectSale;
use App\Models\ClinicSetting;
use App\Models\DirectSaleItem;
use App\Models\MedicineBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectSaleController extends Controller
{
    private function adjustBrandStock(MedicineBrand $brand, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $hasBatches = $brand->relationLoaded('batches')
            ? $brand->batches->count() > 0
            : $brand->batches()->exists();

        if ($hasBatches) {
            $available = (int) $brand->batches->sum('quantity');
            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'items' => ["Insufficient stock for {$brand->name}. Available: {$available}, requested: {$quantity}."]
                ]);
            }

            $remaining = $quantity;
            foreach ($brand->batches as $batch) {
                $batchQty = (int) ($batch->quantity ?? 0);
                if ($batchQty <= 0) {
                    continue;
                }

                $deduct = min($batchQty, $remaining);
                if ($deduct <= 0) {
                    continue;
                }

                $batch->update(['quantity' => max(0, $batchQty - $deduct)]);
                $remaining -= $deduct;

                if ($remaining <= 0) {
                    break;
                }
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'items' => ["Unable to allocate stock for {$brand->name}."]
                ]);
            }

            $brand->update(['stock' => (int) $brand->batches()->sum('quantity')]);
            return;
        }

        $currentStock = (int) ($brand->stock ?? 0);
        if ($currentStock < $quantity) {
            throw ValidationException::withMessages([
                'items' => ["Insufficient stock for {$brand->name}. Available: {$currentStock}, requested: {$quantity}."]
            ]);
        }

        $brand->update(['stock' => max(0, $currentStock - $quantity)]);
    }

    private function formatItem(DirectSaleItem $item)
    {
        return [
            'id' => $item->id,
            'medicineBrandId' => $item->medicine_brand_id,
            'quantity' => (float) $item->quantity,
            'unitPrice' => (float) $item->unit_price,
            'lineTotal' => (float) $item->line_total,
            'brand' => $item->brand ? [
                'id' => $item->brand->id,
                'name' => $item->brand->name,
                'medicine' => $item->brand->relationLoaded('medicine') && $item->brand->medicine
                    ? [
                        'id' => $item->brand->medicine->id,
                        'name' => $item->brand->medicine->name
                    ]
                    : null
            ] : null
        ];
    }

    private function formatSale(DirectSale $sale)
    {
        return [
            'id' => $sale->id,
            'date' => $sale->date,
            'saleReference' => $sale->sale_reference,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'serviceCharge' => (float) $sale->service_charge,
            'total' => (float) $sale->total,
            'paymentType' => $sale->payment_type,
            'paymentStatus' => $sale->payment_status,
            'notes' => $sale->notes,
            'items' => $sale->items->map(fn ($item) => $this->formatItem($item)),
            'createdAt' => $sale->created_at,
            'updatedAt' => $sale->updated_at
        ];
    }

    public function index()
    {
        $request = request();
        $queryText = trim((string) $request->string('q', ''));
        $dateFrom = trim((string) $request->string('dateFrom', ''));
        $dateTo = trim((string) $request->string('dateTo', ''));
        $perPage = max(5, min(100, (int) $request->integer('perPage', 10)));

        $builder = DirectSale::with(['items.brand.medicine'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($queryText !== '') {
            $builder->where(function ($q) use ($queryText) {
                $q->where('sale_reference', 'like', "%{$queryText}%")
                    ->orWhere('payment_type', 'like', "%{$queryText}%")
                    ->orWhere('payment_status', 'like', "%{$queryText}%")
                    ->orWhereHas('items.brand', function ($brandQ) use ($queryText) {
                        $brandQ->where('name', 'like', "%{$queryText}%")
                            ->orWhereHas('medicine', function ($medicineQ) use ($queryText) {
                                $medicineQ->where('name', 'like', "%{$queryText}%");
                            });
                    });
            });
        }

        if ($dateFrom !== '') {
            $builder->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $builder->whereDate('date', '<=', $dateTo);
        }

        $wantsPaginated = $request->has('page') || $request->has('perPage') || $queryText !== '' || $dateFrom !== '' || $dateTo !== '';

        if ($wantsPaginated) {
            $paginator = $builder->paginate($perPage);
            $paginator->setCollection($paginator->getCollection()->map(fn ($sale) => $this->formatSale($sale)));
            return response()->json($paginator);
        }

        $sales = $builder->get();
        return response()->json($sales->map(fn ($sale) => $this->formatSale($sale)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
            'saleReference' => 'nullable|string|max:120',
            'paymentType' => 'nullable|in:cash,credit',
            'paymentStatus' => 'nullable|in:pending,paid',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicineBrandId' => 'required|integer|exists:medicine_brands,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        return DB::transaction(function () use ($data) {
            $subtotal = 0;

            $sale = DirectSale::create([
                'date' => $data['date'] ?? now(),
                'sale_reference' => $data['saleReference'] ?? null,
                'subtotal' => 0,
                'discount' => $data['discount'] ?? 0,
                'service_charge' => $data['serviceCharge'] ?? 0,
                'total' => 0,
                'payment_type' => $data['paymentType'] ?? 'cash',
                'payment_status' => $data['paymentStatus'] ?? (($data['paymentType'] ?? 'cash') === 'credit' ? 'pending' : 'paid'),
                'notes' => $data['notes'] ?? null
            ]);

            foreach ($data['items'] as $entry) {
                $brand = MedicineBrand::with([
                    'batches' => function ($query) {
                        $query
                            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('expiry_date')
                            ->orderBy('id');
                    }
                ])->lockForUpdate()->find($entry['medicineBrandId']);

                if (! $brand) {
                    continue;
                }

                $quantity = max(0, (int) $entry['quantity']);
                if ($quantity <= 0) {
                    continue;
                }

                $this->adjustBrandStock($brand, $quantity);

                $unitPrice = (float) ($brand->price ?? 0);
                $lineTotal = round($unitPrice * $quantity, 2);

                DirectSaleItem::create([
                    'direct_sale_id' => $sale->id,
                    'medicine_brand_id' => $brand->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ]);

                $subtotal += $lineTotal;
            }

            $discount = (float) ($data['discount'] ?? 0);
            $serviceCharge = (float) ($data['serviceCharge'] ?? 0);
            $total = max(0, round($subtotal - $discount + $serviceCharge, 2));

            $sale->update([
                'subtotal' => round($subtotal, 2),
                'total' => $total
            ]);

            $sale->load(['items.brand.medicine']);

            return response()->json($this->formatSale($sale), 201);
        });
    }
}
