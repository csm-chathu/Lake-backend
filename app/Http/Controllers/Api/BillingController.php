<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DayEndCashClosure;
use App\Models\DirectSale;
use App\Models\Invoice;
use App\Models\InvoiceTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function nextInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $last = Invoice::query()
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $next = 1;
        if ($last && preg_match('/-(\d{4})$/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function refreshInvoiceAmounts(Invoice $invoice): Invoice
    {
        $paymentTotal = (float) $invoice->transactions()->where('type', 'payment')->sum('amount');
        $refundTotal = (float) $invoice->transactions()->where('type', 'refund')->sum('amount');
        $netPaid = max(0, round($paymentTotal - $refundTotal, 2));

        $total = (float) $invoice->total;
        $due = max(0, round($total - $netPaid, 2));

        $status = 'unpaid';
        if ($refundTotal > 0 && $netPaid <= 0) {
            $status = 'refunded';
        } elseif ($due <= 0.00001 && $refundTotal > 0) {
            $status = 'partially_refunded';
        } elseif ($due <= 0.00001) {
            $status = 'paid';
        } elseif ($netPaid > 0) {
            $status = 'partially_paid';
        }

        $invoice->update([
            'paid_amount' => round($paymentTotal, 2),
            'refunded_amount' => round($refundTotal, 2),
            'due_amount' => round($due, 2),
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'invoiceDate' => $invoice->invoice_date,
            'sourceType' => $invoice->source_type,
            'sourceId' => $invoice->source_id,
            'patientId' => $invoice->patient_id,
            'ownerId' => $invoice->owner_id,
            'customerName' => $invoice->customer_name,
            'subtotal' => (float) $invoice->subtotal,
            'discount' => (float) $invoice->discount,
            'tax' => (float) $invoice->tax,
            'total' => (float) $invoice->total,
            'paidAmount' => (float) $invoice->paid_amount,
            'refundedAmount' => (float) $invoice->refunded_amount,
            'dueAmount' => (float) $invoice->due_amount,
            'status' => $invoice->status,
            'lineItems' => $invoice->line_items ?? [],
            'notes' => $invoice->notes,
            'createdAt' => $invoice->created_at,
            'updatedAt' => $invoice->updated_at,
        ];
    }

    private function invoiceCashInForDate(string $date): float
    {
        return (float) InvoiceTransaction::query()
            ->where('method', 'cash')
            ->where('type', 'payment')
            ->whereDate('transaction_date', $date)
            ->sum('amount');
    }

    private function directSaleCashInForDate(string $date): float
    {
        return (float) DirectSale::query()
            ->where('payment_type', 'cash')
            ->where('payment_status', 'paid')
            ->whereDate('date', $date)
            ->sum('total');
    }

    public function invoices(Request $request)
    {
        $query = Invoice::query()->orderByDesc('invoice_date')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('sourceType')) {
            $query->where('source_type', $request->string('sourceType'));
        }

        $invoices = $query->get();
        return response()->json($invoices->map(fn (Invoice $inv) => $this->formatInvoice($inv)));
    }

    public function showInvoice(Invoice $invoice)
    {
        return response()->json([
            ...$this->formatInvoice($invoice),
            'transactions' => $invoice->transactions()
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (InvoiceTransaction $tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'amount' => (float) $tx->amount,
                    'method' => $tx->method,
                    'transactionDate' => $tx->transaction_date,
                    'reference' => $tx->reference,
                    'userId' => $tx->user_id,
                    'notes' => $tx->notes,
                    'createdAt' => $tx->created_at,
                ]),
        ]);
    }

    public function createInvoice(Request $request)
    {
        $data = $request->validate([
            'sourceType' => 'nullable|in:manual,appointment,direct_sale',
            'sourceId' => 'nullable|integer|min:1',
            'invoiceDate' => 'nullable|date',
            'customerName' => 'nullable|string|max:255',
            'subtotal' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'lineItems' => 'nullable|array|max:100',
            'notes' => 'nullable|string',
        ]);

        $sourceType = $data['sourceType'] ?? 'manual';
        $sourceId = $data['sourceId'] ?? null;
        $subtotal = (float) ($data['subtotal'] ?? 0);
        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $total = (float) ($data['total'] ?? max(0, round($subtotal - $discount + $tax, 2)));
        $patientId = null;
        $ownerId = null;
        $customerName = $data['customerName'] ?? null;

        if ($sourceType === 'appointment') {
            $appointment = Appointment::with('patient.owner')->findOrFail($sourceId);
            $subtotal = (float) $appointment->total_charge;
            $discount = (float) $appointment->discount;
            $tax = 0;
            $total = max(0, round($subtotal - $discount, 2));
            $patientId = $appointment->patient_id;
            $ownerId = optional($appointment->patient)->owner_id;
            if (! $customerName && $appointment->patient) {
                $customerName = trim(($appointment->patient->name ?? '') . ' / ' . (($appointment->patient->owner->first_name ?? '') . ' ' . ($appointment->patient->owner->last_name ?? '')));
            }
        } elseif ($sourceType === 'direct_sale') {
            $sale = DirectSale::findOrFail($sourceId);
            $subtotal = (float) $sale->subtotal;
            $discount = (float) $sale->discount;
            $tax = 0;
            $total = (float) $sale->total;
        }

        $invoice = Invoice::create([
            'invoice_number' => $this->nextInvoiceNumber(),
            'invoice_date' => isset($data['invoiceDate']) ? Carbon::parse($data['invoiceDate']) : now(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'patient_id' => $patientId,
            'owner_id' => $ownerId,
            'customer_name' => $customerName,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
            'paid_amount' => 0,
            'refunded_amount' => 0,
            'due_amount' => round($total, 2),
            'status' => 'unpaid',
            'line_items' => $data['lineItems'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json($this->formatInvoice($invoice), 201);
    }

    public function addPayment(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|in:cash,card,bank_transfer,upi,other',
            'transactionDate' => 'nullable|date',
            'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($invoice, $data, $request) {
            InvoiceTransaction::create([
                'invoice_id' => $invoice->id,
                'type' => 'payment',
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? 'cash',
                'transaction_date' => isset($data['transactionDate']) ? Carbon::parse($data['transactionDate']) : now(),
                'reference' => $data['reference'] ?? null,
                'user_id' => optional($request->user())->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->refreshInvoiceAmounts($invoice->fresh());
        });

        return response()->json($this->formatInvoice($invoice->fresh()));
    }

    public function addRefund(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|in:cash,card,bank_transfer,upi,other',
            'transactionDate' => 'nullable|date',
            'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($invoice, $data, $request) {
            InvoiceTransaction::create([
                'invoice_id' => $invoice->id,
                'type' => 'refund',
                'amount' => round((float) $data['amount'], 2),
                'method' => $data['method'] ?? 'cash',
                'transaction_date' => isset($data['transactionDate']) ? Carbon::parse($data['transactionDate']) : now(),
                'reference' => $data['reference'] ?? null,
                'user_id' => optional($request->user())->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->refreshInvoiceAmounts($invoice->fresh());
        });

        return response()->json($this->formatInvoice($invoice->fresh()));
    }

    public function dayEndSummary(Request $request)
    {
        $date = $request->filled('date') ? Carbon::parse($request->string('date'))->toDateString() : now()->toDateString();

        $invoiceCashIn = $this->invoiceCashInForDate($date);
        $directSaleCashIn = $this->directSaleCashInForDate($date);
        $cashIn = $invoiceCashIn + $directSaleCashIn;

        $cashOut = (float) InvoiceTransaction::query()
            ->where('method', 'cash')
            ->where('type', 'refund')
            ->whereDate('transaction_date', $date)
            ->sum('amount');

        return response()->json([
            'businessDate' => $date,
            'cashIn' => round($cashIn, 2),
            'invoiceCashIn' => round($invoiceCashIn, 2),
            'directSalesCashIn' => round($directSaleCashIn, 2),
            'cashOut' => round($cashOut, 2),
            'netCash' => round($cashIn - $cashOut, 2),
            'alreadyClosed' => DayEndCashClosure::whereDate('business_date', $date)->exists(),
        ]);
    }

    public function closeDay(Request $request)
    {
        $data = $request->validate([
            'businessDate' => 'nullable|date',
            'openingCash' => 'nullable|numeric',
            'countedCash' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $businessDate = isset($data['businessDate']) ? Carbon::parse($data['businessDate'])->toDateString() : now()->toDateString();
        $openingCash = round((float) ($data['openingCash'] ?? 0), 2);

        $invoiceCashIn = $this->invoiceCashInForDate($businessDate);
        $directSaleCashIn = $this->directSaleCashInForDate($businessDate);
        $cashIn = $invoiceCashIn + $directSaleCashIn;

        $cashOut = (float) InvoiceTransaction::query()
            ->where('method', 'cash')
            ->where('type', 'refund')
            ->whereDate('transaction_date', $businessDate)
            ->sum('amount');

        $expected = round($openingCash + $cashIn - $cashOut, 2);
        $counted = round((float) $data['countedCash'], 2);
        $variance = round($counted - $expected, 2);

        $closure = DayEndCashClosure::updateOrCreate(
            ['business_date' => $businessDate],
            [
                'opening_cash' => $openingCash,
                'cash_in' => round($cashIn, 2),
                'cash_out' => round($cashOut, 2),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'variance' => $variance,
                'closed_by' => optional($request->user())->id,
                'closed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'id' => $closure->id,
            'businessDate' => $closure->business_date,
            'openingCash' => (float) $closure->opening_cash,
            'cashIn' => (float) $closure->cash_in,
            'invoiceCashIn' => round($invoiceCashIn, 2),
            'directSalesCashIn' => round($directSaleCashIn, 2),
            'cashOut' => (float) $closure->cash_out,
            'expectedCash' => (float) $closure->expected_cash,
            'countedCash' => (float) $closure->counted_cash,
            'variance' => (float) $closure->variance,
            'closedAt' => $closure->closed_at,
            'notes' => $closure->notes,
        ]);
    }
}
