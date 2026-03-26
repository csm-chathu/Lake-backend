<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medicine_brand_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_brand_id')->constrained('medicine_brands'); // removed cascadeOnDelete
            $table->string('batch_number', 120)->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->index(['medicine_brand_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_brand_batches');
    }
};
