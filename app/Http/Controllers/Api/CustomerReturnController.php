<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\MedicineBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerReturnController extends Controller
{
    // ── Helpers ────────────────────────────────────────────────────────────

    private function nextReference(): string
    {
        $last = CustomerReturn::orderByDesc('id')->value('return_reference');
        if ($last && preg_match('/RET-(\d+)$/', $last, $m)) {
            return 'RET-' . str_pad((int)$m[1] + 1, 5, '0', STR_PAD_LEFT);
        }
        return 'RET-00001';
    }

    private function formatReturn(CustomerReturn $ret): array
    {
        return [
            'id'              => $ret->id,
            'returnReference' => $ret->return_reference,
            'returnDate'      => $ret->return_date?->toDateString(),
            'customerName'    => $ret->customer_name,
            'originalSaleRef' => $ret->original_sale_ref,
            'reason'          => $ret->reason,
            'status'          => $ret->status,
            'refundMethod'    => $ret->refund_method,
            'refundAmount'    => (float) $ret->refund_amount,
            'notes'           => $ret->notes,
            'createdAt'       => $ret->created_at,
            'items'           => $ret->items->map(fn($i) => [
                'id'               => $i->id,
                'medicineBrandId'  => $i->medicine_brand_id,
                'description'      => $i->description,
                'quantity'         => (float) $i->quantity,
                'unitPrice'        => (float) $i->unit_price,
                'lineTotal'        => (float) $i->line_total,
                'isDamaged'        => (bool) $i->is_damaged,
                'restockedAt'      => $i->restocked_at,
                'itemReason'       => $i->item_reason,
                'brand'            => $i->medicineBrand ? [
                    'id'   => $i->medicineBrand->id,
                    'name' => $i->medicineBrand->name,
                ] : null,
            ])->values(),
        ];
    }

    private function restockItemIfEligible(CustomerReturnItem $item): void
    {
        if ((bool) $item->is_damaged || $item->restocked_at || ! $item->medicine_brand_id) {
            return;
        }

        $quantity = (float) ($item->quantity ?? 0);
        if ($quantity <= 0) {
            $item->update(['restocked_at' => now()]);
            return;
        }

        $brand = MedicineBrand::with(['batches' => function ($query) {
            $query->orderBy('id');
        }])->lockForUpdate()->find($item->medicine_brand_id);

        if (! $brand) {
            return;
        }

        if ($brand->batches->count() > 0) {
            $batch = $brand->batches->first();
            $currentBatchQty = (float) ($batch->quantity ?? 0);
            $batch->update(['quantity' => round($currentBatchQty + $quantity, 2)]);
            $brand->update(['stock' => (float) $brand->batches()->sum('quantity')]);
        } else {
            $currentStock = (float) ($brand->stock ?? 0);
            $brand->update(['stock' => round($currentStock + $quantity, 2)]);
        }

        $item->update(['restocked_at' => now()]);
    }

    private function restockEligibleItems(CustomerReturn $ret): void
    {
        $ret->loadMissing('items');
        foreach ($ret->items as $item) {
            $this->restockItemIfEligible($item);
        }
    }

    // ── Index ───────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $perPage  = min((int)($request->query('per_page', 15)), 100);
        $query    = $request->query('q');
        $status   = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        $builder = CustomerReturn::with('items.medicineBrand')
            ->orderByDesc('return_date')
            ->orderByDesc('id');

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('return_reference', 'like', "%$query%")
                  ->orWhere('customer_name', 'like', "%$query%")
                  ->orWhere('original_sale_ref', 'like', "%$query%");
            });
        }
        if ($status) {
            $builder->where('status', $status);
        }
        if ($dateFrom) {
            $builder->whereDate('return_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $builder->whereDate('return_date', '<=', $dateTo);
        }

        $paginated = $builder->paginate($perPage);

        return response()->json([
            'data'      => collect($paginated->items())->map(fn ($row) => $this->formatReturn($row))->values(),
            'total'     => $paginated->total(),
            'per_page'  => $paginated->perPage(),
            'page'      => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    // ── Store ───────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'returnDate'      => 'required|date',
            'customerName'    => 'nullable|string|max:180',
            'originalSaleRef' => 'nullable|string|max:120',
            'reason'          => 'nullable|string',
            'status'          => 'nullable|in:pending,approved,rejected',
            'refundMethod'    => 'nullable|in:cash,credit,exchange,none',
            'refundAmount'    => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'items'           => 'nullable|array',
            'items.*.description'  => 'nullable|string|max:255',
            'items.*.medicineBrandId' => 'nullable|integer',
            'items.*.quantity'     => 'nullable|numeric|min:0',
            'items.*.unitPrice'    => 'nullable|numeric|min:0',
            'items.*.isDamaged'    => 'nullable|boolean',
            'items.*.itemReason'   => 'nullable|string',
        ]);

        $ret = DB::transaction(function () use ($data) {
            $refundAmount = $data['refundAmount'] ?? 0;

            // Auto-calculate refund from items if not provided
            $rawItems = $data['items'] ?? [];
            if (!isset($data['refundAmount']) || $refundAmount == 0) {
                $refundAmount = collect($rawItems)->sum(fn($i) =>
                    (float)($i['quantity'] ?? 1) * (float)($i['unitPrice'] ?? 0)
                );
            }

            $ret = CustomerReturn::create([
                'return_reference' => $this->nextReference(),
                'return_date'      => $data['returnDate'],
                'customer_name'    => $data['customerName'] ?? null,
                'original_sale_ref'=> $data['originalSaleRef'] ?? null,
                'reason'           => $data['reason'] ?? null,
                'status'           => $data['status'] ?? 'pending',
                'refund_method'    => $data['refundMethod'] ?? 'cash',
                'refund_amount'    => $refundAmount,
                'notes'            => $data['notes'] ?? null,
            ]);

            foreach ($rawItems as $li) {
                $qty   = (float)($li['quantity'] ?? 1);
                $price = (float)($li['unitPrice'] ?? 0);
                CustomerReturnItem::create([
                    'customer_return_id' => $ret->id,
                    'medicine_brand_id'  => $li['medicineBrandId'] ? (int)$li['medicineBrandId'] : null,
                    'description'        => $li['description'] ?? null,
                    'quantity'           => $qty,
                    'unit_price'         => $price,
                    'line_total'         => round($qty * $price, 2),
                    'is_damaged'         => (bool) ($li['isDamaged'] ?? false),
                    'item_reason'        => $li['itemReason'] ?? null,
                ]);
            }

            $this->restockEligibleItems($ret);
            $ret->load('items.medicineBrand');
            return $ret;
        });

        return response()->json($this->formatReturn($ret), 201);
    }

    // ── Show ────────────────────────────────────────────────────────────────

    public function show(CustomerReturn $customerReturn)
    {
        $customerReturn->load('items.medicineBrand');
        return response()->json($this->formatReturn($customerReturn));
    }

    // ── Update (status change / edit) ───────────────────────────────────────

    public function update(Request $request, CustomerReturn $customerReturn)
    {
        $data = $request->validate([
            'returnDate'      => 'sometimes|date',
            'customerName'    => 'nullable|string|max:180',
            'originalSaleRef' => 'nullable|string|max:120',
            'reason'          => 'nullable|string',
            'status'          => 'nullable|in:pending,approved,rejected',
            'refundMethod'    => 'nullable|in:cash,credit,exchange,none',
            'refundAmount'    => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        DB::transaction(function () use ($customerReturn, $data) {
            $customerReturn->update(array_filter([
                'return_date'       => $data['returnDate'] ?? null,
                'customer_name'     => $data['customerName'] ?? null,
                'original_sale_ref' => $data['originalSaleRef'] ?? null,
                'reason'            => $data['reason'] ?? null,
                'status'            => $data['status'] ?? null,
                'refund_method'     => $data['refundMethod'] ?? null,
                'refund_amount'     => $data['refundAmount'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ], fn($v) => $v !== null));

            $this->restockEligibleItems($customerReturn);
        });

        $customerReturn->load('items.medicineBrand');
        return response()->json($this->formatReturn($customerReturn));
    }

    // ── Destroy ─────────────────────────────────────────────────────────────

    public function destroy(CustomerReturn $customerReturn)
    {
        $customerReturn->delete();
        return response()->json(null, 204);
    }
}
