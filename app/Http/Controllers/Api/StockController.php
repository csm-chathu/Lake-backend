<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockItem;
use App\Models\StockBatch;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        return StockItem::with(['batches' => function ($query) {
            $query->orderBy('expiry_date')->orderBy('id');
        }])->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'quantity' => 'required|integer',
            'purchase_price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $item = StockItem::create($data);
        return response()->json($item, 201);
    }

    public function show($id)
    {
        return StockItem::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $item = StockItem::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'quantity' => 'required|integer',
            'purchase_price' => 'nullable|numeric',
            'sale_price' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $item->update($data);
        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = StockItem::findOrFail($id);
        $item->delete();
        return response()->json(['deleted' => true]);
    }

    public function batches($id)
    {
        $item = StockItem::findOrFail($id);
        return $item->batches()->orderBy('expiry_date')->orderBy('id')->get();
    }

    public function storeBatch(Request $request, $id)
    {
        $item = StockItem::findOrFail($id);

        $data = $request->validate([
            'batch_number' => 'nullable|string|max:120',
            'expiry_date' => 'nullable|date',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $batch = DB::transaction(function () use ($item, $data) {
            $batch = $item->batches()->create([
                'batch_number' => $data['batch_number'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'quantity' => $data['quantity'],
                'cost_price' => $data['cost_price'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $before = (int) $item->quantity;
            $after = $before + (int) $data['quantity'];
            $item->update(['quantity' => $after]);

            StockAdjustment::create([
                'stock_item_id' => $item->id,
                'stock_batch_id' => $batch->id,
                'type' => 'in',
                'quantity' => (int) $data['quantity'],
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reason' => 'Batch created',
            ]);

            return $batch;
        });

        return response()->json($batch, 201);
    }

    public function updateBatch(Request $request, $id, $batchId)
    {
        $item = StockItem::findOrFail($id);
        $batch = StockBatch::where('stock_item_id', $item->id)->findOrFail($batchId);

        $data = $request->validate([
            'batch_number' => 'nullable|string|max:120',
            'expiry_date' => 'nullable|date',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $updatedBatch = DB::transaction(function () use ($item, $batch, $data) {
            $oldBatchQty = (int) $batch->quantity;
            $newBatchQty = (int) $data['quantity'];
            $delta = $newBatchQty - $oldBatchQty;

            $before = (int) $item->quantity;
            $after = max(0, $before + $delta);

            $batch->update([
                'batch_number' => $data['batch_number'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'quantity' => $newBatchQty,
                'cost_price' => $data['cost_price'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $item->update(['quantity' => $after]);

            if ($delta !== 0) {
                StockAdjustment::create([
                    'stock_item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'type' => $delta > 0 ? 'in' : 'out',
                    'quantity' => abs($delta),
                    'before_quantity' => $before,
                    'after_quantity' => $after,
                    'reason' => 'Batch updated',
                ]);
            }

            return $batch->fresh();
        });

        return response()->json($updatedBatch);
    }

    public function destroyBatch($id, $batchId)
    {
        $item = StockItem::findOrFail($id);
        $batch = StockBatch::where('stock_item_id', $item->id)->findOrFail($batchId);

        DB::transaction(function () use ($item, $batch) {
            $before = (int) $item->quantity;
            $batchQty = (int) $batch->quantity;
            $after = max(0, $before - $batchQty);

            $item->update(['quantity' => $after]);

            if ($batchQty > 0) {
                StockAdjustment::create([
                    'stock_item_id' => $item->id,
                    'stock_batch_id' => $batch->id,
                    'type' => 'out',
                    'quantity' => $batchQty,
                    'before_quantity' => $before,
                    'after_quantity' => $after,
                    'reason' => 'Batch deleted',
                ]);
            }

            $batch->delete();
        });

        return response()->json(['deleted' => true]);
    }

    public function adjust(Request $request, $id)
    {
        $item = StockItem::findOrFail($id);

        $data = $request->validate([
            'type' => 'required|in:in,out,set',
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
            'stock_batch_id' => 'nullable|integer|exists:stock_batches,id',
        ]);

        $result = DB::transaction(function () use ($item, $data) {
            $before = (int) $item->quantity;
            $quantity = (int) $data['quantity'];

            $after = $before;
            if ($data['type'] === 'in') {
                $after = $before + $quantity;
            } elseif ($data['type'] === 'out') {
                $after = max(0, $before - $quantity);
            } elseif ($data['type'] === 'set') {
                $after = $quantity;
            }

            $item->update(['quantity' => $after]);

            if (!empty($data['stock_batch_id'])) {
                $batch = StockBatch::where('stock_item_id', $item->id)->findOrFail($data['stock_batch_id']);
                $batchBefore = (int) $batch->quantity;

                if ($data['type'] === 'in') {
                    $batch->quantity = $batchBefore + $quantity;
                } elseif ($data['type'] === 'out') {
                    $batch->quantity = max(0, $batchBefore - $quantity);
                } else {
                    $batch->quantity = $quantity;
                }

                $batch->save();
            }

            $adjustment = StockAdjustment::create([
                'stock_item_id' => $item->id,
                'stock_batch_id' => $data['stock_batch_id'] ?? null,
                'type' => $data['type'],
                'quantity' => $quantity,
                'before_quantity' => $before,
                'after_quantity' => $after,
                'reason' => $data['reason'] ?? null,
            ]);

            return [
                'item' => $item->fresh(),
                'adjustment' => $adjustment,
            ];
        });

        return response()->json($result);
    }

    public function adjustments($id)
    {
        $item = StockItem::findOrFail($id);
        return $item->adjustments()->orderByDesc('id')->limit(50)->get();
    }
}
