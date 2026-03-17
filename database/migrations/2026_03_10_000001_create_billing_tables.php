<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->dateTime('invoice_date');
            $table->enum('source_type', ['manual', 'appointment', 'direct_sale'])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'partially_refunded', 'refunded'])->default('unpaid');
            $table->json('line_items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['invoice_date']);
        });

        Schema::create('invoice_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->enum('type', ['payment', 'refund']);
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'card', 'bank_transfer', 'upi', 'other'])->default('cash');
            $table->dateTime('transaction_date');
            $table->string('reference', 120)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_date']);
            $table->index(['type', 'method']);
        });

        Schema::create('day_end_cash_closures', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('cash_in', 12, 2)->default(0);
            $table->decimal('cash_out', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->default(0);
            $table->decimal('variance', 12, 2)->default(0);
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->dateTime('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_end_cash_closures');
        Schema::dropIfExists('invoice_transactions');
        Schema::dropIfExists('invoices');
    }
};
