<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Medicine;
use App\Models\MedicineBrand;
use App\Models\MedicineBrandBatch;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function nextNumber(string $prefix, string $column, string $modelClass): string
    {
        $date = now()->format('Ymd');
        $base = "{$prefix}-{$date}-";
        $last = $modelClass::query()
            ->where($column, 'like', $base . '%')
            ->orderByDesc('id')
            ->value($column);

        $next = 1;
        if ($last && preg_match('/-(\d{4})$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeItems(array $items): array
    {
        return array_values(array_map(function (array $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unitCost = round((float) ($item['unitCost'] ?? 0), 2);
            $discount = round((float) ($item['discount'] ?? 0), 2);
            $lineTotal = round(max(0, ($qty * $unitCost) - $discount), 2);

            return [
                'stockItemId' => isset($item['stockItemId']) ? (int) $item['stockItemId'] : null,
                'medicineBrandId' => isset($item['medicineBrandId']) ? (int) $item['medicineBrandId'] : null,
                'description' => $item['description'] ?? null,
                'quantity' => $qty,
                'unitCost' => $unitCost,
                'unitType' => $item['unitType'] ?? 'unit',
                'scale' => $item['scale'] ?? 'ml',
                'conversion' => (int) ($item['conversion'] ?? 1),
                'wholesalePrice' => isset($item['wholesalePrice']) ? round((float) $item['wholesalePrice'], 2) : null,
                'sellingPrice' => isset($item['sellingPrice']) ? round((float) $item['sellingPrice'], 2) : null,
                'barcode' => !empty($item['barcode']) ? trim((string) $item['barcode']) : null,
                'discount' => $discount,
                'lineTotal' => $lineTotal,
                'batchNumber' => $item['batchNumber'] ?? null,
                'expiryDate' => $item['expiryDate'] ?? null,
            ];
        }, $items));
    }

    private function calcTotals(array $items, float $discount = 0, float $tax = 0): array
    {
        $subtotal = array_reduce($items, fn ($carry, $item) => $carry + (float) ($item['lineTotal'] ?? 0), 0.0);
        $total = max(0, round($subtotal - $discount + $tax, 2));

        return [
            'subtotal' => round($subtotal, 2),
            'total' => $total,
        ];
    }

    private function resolveBrandForGoodsReceipt(array $item): MedicineBrand
    {
        if (!empty($item['medicineBrandId'])) {
            return MedicineBrand::lockForUpdate()->findOrFail((int) $item['medicineBrandId']);
        }

        $itemName = trim((string) ($item['description'] ?? ''));
        if ($itemName !== '') {
            $medicine = Medicine::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])
                ->first();

            if (!$medicine) {
                $medicine = Medicine::create([
                    'name' => $itemName,
                    'description' => null,
                ]);
            }

            $brand = MedicineBrand::query()->where('medicine_id', $medicine->id)->orderBy('id')->first();
            if (!$brand) {
                $brand = MedicineBrand::create([
                    'medicine_id' => $medicine->id,
                    'name' => 'Default',
                    'price' => 0,
                    'wholesale_price' => 0,
                    'stock' => 0,
                    'supplier_id' => null,
                    'batch_number' => null,
                    'barcode' => null,
                    'expiry_date' => null,
                ]);
            }

            return MedicineBrand::lockForUpdate()->findOrFail($brand->id);
        }

        abort(422, 'Each GRN item must include item name or variant.');
    }

    private function recalcSupplierInvoice(SupplierInvoice $invoice): SupplierInvoice
    {
        $due = max(0, round((float) $invoice->total - (float) $invoice->paid_amount - (float) $invoice->credited_amount, 2));
        $status = 'unpaid';
        if ($due <= 0.00001) {
            $status = 'paid';
        } elseif ((float) $invoice->paid_amount > 0 || (float) $invoice->credited_amount > 0) {
            $status = 'partially_paid';
        }

        $invoice->update([
            'due_amount' => round($due, 2),
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    public function purchaseOrders()
    {
        return response()->json(
            PurchaseOrder::with('supplier')->orderByDesc('order_date')->orderByDesc('id')->get()
        );
    }

    public function createPurchaseOrder(Request $request)
    {
        $data = $request->validate([
            'supplierId' => 'required|integer|exists:suppliers,id',
            'orderDate' => 'nullable|date',
            'expectedDate' => 'nullable|date',
            'status' => 'nullable|in:draft,sent,partially_received,received,cancelled',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1|max:500',
            'items.*.stockItemId' => 'required|integer|exists:stock_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unitCost' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $items = $this->normalizeItems($data['items']);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $tax = round((float) ($data['tax'] ?? 0), 2);
        $totals = $this->calcTotals($items, $discount, $tax);

        $po = PurchaseOrder::create([
            'po_number' => $this->nextNumber('PO', 'po_number', PurchaseOrder::class),
            'supplier_id' => (int) $data['supplierId'],
            'order_date' => isset($data['orderDate']) ? Carbon::parse($data['orderDate'])->toDateString() : now()->toDateString(),
            'expected_date' => $data['expectedDate'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'subtotal' => $totals['subtotal'],
            'discount' => $discount,
            'tax' => $tax,
            'total' => $totals['total'],
            'items' => $items,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($po->load('supplier'), 201);
    }

    public function showPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        return response()->json($purchaseOrder->load('supplier'));
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'supplierId' => 'nullable|integer|exists:suppliers,id',
            'orderDate' => 'nullable|date',
            'expectedDate' => 'nullable|date',
            'status' => 'nullable|in:draft,sent,partially_received,received,cancelled',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'nullable|array|min:1|max:500',
            'items.*.stockItemId' => 'required_with:items|integer|exists:stock_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unitCost' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $items = $purchaseOrder->items ?? [];
        if (isset($data['items'])) {
            $items = $this->normalizeItems($data['items']);
        }

        $discount = round((float) ($data['discount'] ?? $purchaseOrder->discount), 2);
        $tax = round((float) ($data['tax'] ?? $purchaseOrder->tax), 2);
        $totals = $this->calcTotals($items, $discount, $tax);

        $purchaseOrder->update([
            'supplier_id' => $data['supplierId'] ?? $purchaseOrder->supplier_id,
            'order_date' => isset($data['orderDate']) ? Carbon::parse($data['orderDate'])->toDateString() : $purchaseOrder->order_date,
            'expected_date' => array_key_exists('expectedDate', $data) ? $data['expectedDate'] : $purchaseOrder->expected_date,
            'status' => $data['status'] ?? $purchaseOrder->status,
            'subtotal' => $totals['subtotal'],
            'discount' => $discount,
            'tax' => $tax,
            'total' => $totals['total'],
            'items' => $items,
            'notes' => $data['notes'] ?? $purchaseOrder->notes,
        ]);

        return response()->json($purchaseOrder->fresh()->load('supplier'));
    }

    public function goodsReceipts(Request $request)
    {
        $perPage = max(5, min(100, (int) $request->integer('perPage', 10)));
        $query = trim((string) $request->string('q', ''));

        $builder = GoodsReceipt::with(['supplier', 'purchaseOrder'])
            ->orderByDesc('receipt_date')
            ->orderByDesc('id');

        if ($query !== '') {
            $builder->where(function ($q) use ($query) {
                $q->where('grn_number', 'like', "%{$query}%")
                    ->orWhere('notes', 'like', "%{$query}%")
                    ->orWhereHas('supplier', function ($supplierQ) use ($query) {
                        $supplierQ->where('name', 'like', "%{$query}%")
                            ->orWhere('contact_person', 'like', "%{$query}%")
                            ->orWhere('phone', 'like', "%{$query}%");
                    });
            });
        }

        return response()->json($builder->paginate($perPage));
    }

    public function createGoodsReceipt(Request $request)
    {
        $data = $request->validate([
            'supplierId' => 'required|integer|exists:suppliers,id',
            'purchaseOrderId' => 'nullable|integer|exists:purchase_orders,id',
            'receiptDate' => 'nullable|date',
            'items' => 'required|array|min:1|max:500',
            'items.*.medicineBrandId' => 'nullable|integer|exists:medicine_brands,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unitCost' => 'nullable|numeric|min:0',
            'items.*.unitType' => 'nullable|string|max:50',
            'items.*.scale' => 'nullable|string|max:50',
            'items.*.conversion' => 'nullable|numeric|min:1',
            'items.*.wholesalePrice' => 'nullable|numeric|min:0',
            'items.*.sellingPrice' => 'nullable|numeric|min:0',
            'items.*.barcode' => 'nullable|string|max:120',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.batchNumber' => 'nullable|string|max:120',
            'items.*.expiryDate' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $items = $this->normalizeItems($data['items']);

        $receipt = DB::transaction(function () use ($data, $items) {
            $totalCost = 0.0;

            $grn = GoodsReceipt::create([
                'grn_number' => $this->nextNumber('GRN', 'grn_number', GoodsReceipt::class),
                'supplier_id' => (int) $data['supplierId'],
                'purchase_order_id' => $data['purchaseOrderId'] ?? null,
                'receipt_date' => isset($data['receiptDate']) ? Carbon::parse($data['receiptDate'])->toDateString() : now()->toDateString(),
                'total_cost' => 0,
                'items' => $items,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $index => $item) {
                $brand = $this->resolveBrandForGoodsReceipt($item);
                $qty = (int) $item['quantity'];
                $before = (int) ($brand->stock ?? 0);
                $after = $before + $qty;

                $brandUpdate = ['stock' => $after];
                if (isset($item['wholesalePrice']) && $item['wholesalePrice'] !== null) {
                    $brandUpdate['wholesale_price'] = (float) $item['wholesalePrice'];
                }
                if (isset($item['sellingPrice']) && $item['sellingPrice'] !== null) {
                    $brandUpdate['price'] = (float) $item['sellingPrice'];
                }
                if (!empty($item['barcode'])) {
                    $brandUpdate['barcode'] = $item['barcode'];
                }
                if (!empty($item['unitType'])) {
                    $brandUpdate['unit_type'] = $item['unitType'];
                }
                if (!empty($item['scale'])) {
                    $brandUpdate['scale'] = $item['scale'];
                }
                if (!empty($item['conversion'])) {
                    $brandUpdate['conversion'] = (int) $item['conversion'];
                }
                if (isset($item['unitCost']) && $item['unitCost'] !== null) {
                    $brandUpdate['unit_cost'] = (float) $item['unitCost'];
                }
                $brand->update($brandUpdate);

                if (!empty($item['batchNumber'])) {
                    MedicineBrandBatch::create([
                        'medicine_brand_id' => $brand->id,
                        'batch_number' => $item['batchNumber'],
                        'expiry_date' => $item['expiryDate'] ?? null,
                        'quantity' => $qty,
                        'barcode' => $item['barcode'] ?? null,
                        'supplier_id' => (int) $data['supplierId'],
                    ]);
                }

                $brand->loadMissing('medicine');
                $items[$index]['medicineBrandId'] = $brand->id;
                if (empty($items[$index]['description'])) {
                    $items[$index]['description'] = $brand->medicine->name ?? $brand->name ?? 'Item';
                }
                if (empty($items[$index]['sellingPrice'])) {
                    $items[$index]['sellingPrice'] = isset($brand->price) ? (float) $brand->price : null;
                }
                if (empty($items[$index]['wholesalePrice'])) {
                    $items[$index]['wholesalePrice'] = isset($brand->wholesale_price) ? (float) $brand->wholesale_price : null;
                }
                if (empty($items[$index]['barcode'])) {
                    $items[$index]['barcode'] = $brand->barcode ?? null;
                }

                $totalCost += (float) ($item['lineTotal'] ?? 0);
            }

            $grn->update([
                'total_cost' => round($totalCost, 2),
                'items' => $items,
            ]);

            if (!empty($data['purchaseOrderId'])) {
                PurchaseOrder::whereKey($data['purchaseOrderId'])->update(['status' => 'received']);
            }

            return $grn->fresh(['supplier', 'purchaseOrder']);
        });

        return response()->json($receipt, 201);
    }

    public function showGoodsReceipt(GoodsReceipt $goodsReceipt)
    {
        return response()->json($goodsReceipt->load(['supplier', 'purchaseOrder']));
    }

    public function supplierInvoices()
    {
        return response()->json(
            SupplierInvoice::with(['supplier'])->orderByDesc('invoice_date')->orderByDesc('id')->get()
        );
    }

    public function createSupplierInvoice(Request $request)
    {
        $data = $request->validate([
            'supplierInvoiceNumber' => 'required|string|max:60|unique:supplier_invoices,supplier_invoice_number',
            'supplierId' => 'required|integer|exists:suppliers,id',
            'purchaseOrderId' => 'nullable|integer|exists:purchase_orders,id',
            'goodsReceiptId' => 'nullable|integer|exists:goods_receipts,id',
            'invoiceDate' => 'nullable|date',
            'dueDate' => 'nullable|date',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1|max:500',
            'items.*.stockItemId' => 'required|integer|exists:stock_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unitCost' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $items = $this->normalizeItems($data['items']);
        $tax = round((float) ($data['tax'] ?? 0), 2);
        $totals = $this->calcTotals($items, 0, $tax);

        $invoice = SupplierInvoice::create([
            'supplier_invoice_number' => $data['supplierInvoiceNumber'],
            'supplier_id' => (int) $data['supplierId'],
            'purchase_order_id' => $data['purchaseOrderId'] ?? null,
            'goods_receipt_id' => $data['goodsReceiptId'] ?? null,
            'invoice_date' => isset($data['invoiceDate']) ? Carbon::parse($data['invoiceDate'])->toDateString() : now()->toDateString(),
            'due_date' => $data['dueDate'] ?? null,
            'subtotal' => $totals['subtotal'],
            'tax' => $tax,
            'total' => $totals['total'],
            'paid_amount' => 0,
            'credited_amount' => 0,
            'due_amount' => $totals['total'],
            'status' => 'unpaid',
            'items' => $items,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($invoice->load('supplier'), 201);
    }

    public function showSupplierInvoice(SupplierInvoice $supplierInvoice)
    {
        return response()->json($supplierInvoice->load('supplier'));
    }

    public function updateSupplierInvoice(Request $request, SupplierInvoice $supplierInvoice)
    {
        $data = $request->validate([
            'dueDate' => 'nullable|date',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'nullable|array|min:1|max:500',
            'items.*.stockItemId' => 'required_with:items|integer|exists:stock_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unitCost' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $items = $supplierInvoice->items ?? [];
        if (isset($data['items'])) {
            $items = $this->normalizeItems($data['items']);
        }

        $tax = round((float) ($data['tax'] ?? $supplierInvoice->tax), 2);
        $totals = $this->calcTotals($items, 0, $tax);

        $supplierInvoice->update([
            'due_date' => array_key_exists('dueDate', $data) ? $data['dueDate'] : $supplierInvoice->due_date,
            'tax' => $tax,
            'subtotal' => $totals['subtotal'],
            'total' => $totals['total'],
            'items' => $items,
            'notes' => $data['notes'] ?? $supplierInvoice->notes,
        ]);

        $supplierInvoice = $this->recalcSupplierInvoice($supplierInvoice->fresh());

        return response()->json($supplierInvoice->load('supplier'));
    }

    public function paySupplierInvoice(Request $request, SupplierInvoice $supplierInvoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $amount = round((float) $data['amount'], 2);
        $supplierInvoice->update([
            'paid_amount' => round((float) $supplierInvoice->paid_amount + $amount, 2),
        ]);

        $supplierInvoice = $this->recalcSupplierInvoice($supplierInvoice->fresh());
        return response()->json($supplierInvoice->load('supplier'));
    }

    public function supplierCreditNotes()
    {
        return response()->json(
            SupplierCreditNote::with(['supplier', 'supplierInvoice'])->orderByDesc('credit_date')->orderByDesc('id')->get()
        );
    }

    public function createSupplierCreditNote(Request $request)
    {
        $data = $request->validate([
            'supplierId' => 'required|integer|exists:suppliers,id',
            'supplierInvoiceId' => 'nullable|integer|exists:supplier_invoices,id',
            'creditDate' => 'nullable|date',
            'total' => 'required|numeric|min:0.01',
            'status' => 'nullable|in:open,applied',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array|max:500',
            'items.*.stockItemId' => 'required_with:items|integer|exists:stock_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unitCost' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
            'returnStock' => 'nullable|boolean',
        ]);

        $items = isset($data['items']) ? $this->normalizeItems($data['items']) : [];
        $returnStock = (bool) ($data['returnStock'] ?? true);

        $credit = DB::transaction(function () use ($data, $items, $returnStock) {
            $credit = SupplierCreditNote::create([
                'credit_note_number' => $this->nextNumber('SCN', 'credit_note_number', SupplierCreditNote::class),
                'supplier_id' => (int) $data['supplierId'],
                'supplier_invoice_id' => $data['supplierInvoiceId'] ?? null,
                'credit_date' => isset($data['creditDate']) ? Carbon::parse($data['creditDate'])->toDateString() : now()->toDateString(),
                'total' => round((float) $data['total'], 2),
                'status' => $data['status'] ?? 'open',
                'items' => $items,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($returnStock) {
                foreach ($items as $item) {
                    $stockItem = StockItem::lockForUpdate()->findOrFail((int) $item['stockItemId']);
                    $qty = (int) $item['quantity'];
                    $before = (int) $stockItem->quantity;
                    $after = max(0, $before - $qty);

                    $stockItem->update(['quantity' => $after]);

                    StockAdjustment::create([
                        'stock_item_id' => $stockItem->id,
                        'stock_batch_id' => null,
                        'type' => 'out',
                        'quantity' => $qty,
                        'before_quantity' => $before,
                        'after_quantity' => $after,
                        'reason' => 'Supplier return ' . $credit->credit_note_number,
                    ]);
                }
            }

            if (!empty($data['supplierInvoiceId'])) {
                $invoice = SupplierInvoice::lockForUpdate()->findOrFail((int) $data['supplierInvoiceId']);
                $invoice->update([
                    'credited_amount' => round((float) $invoice->credited_amount + (float) $credit->total, 2),
                ]);
                $this->recalcSupplierInvoice($invoice->fresh());
            }

            return $credit->fresh(['supplier', 'supplierInvoice']);
        });

        return response()->json($credit, 201);
    }

    public function showSupplierCreditNote(SupplierCreditNote $supplierCreditNote)
    {
        return response()->json($supplierCreditNote->load(['supplier', 'supplierInvoice']));
    }
}
