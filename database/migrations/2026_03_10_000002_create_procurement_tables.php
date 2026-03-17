<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_date']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->date('receipt_date');
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['receipt_date']);
            $table->index(['supplier_id']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_invoice_number', 60)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('credited_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_date']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->date('credit_date');
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['open', 'applied'])->default('open');
            $table->json('items')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['credit_date']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_notes');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_orders');
    }
};
