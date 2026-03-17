<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('direct_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direct_sale_id')->constrained('direct_sales')->cascadeOnDelete();
            $table->foreignId('medicine_brand_id')->constrained('medicine_brands')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_sale_items');
    }
};
