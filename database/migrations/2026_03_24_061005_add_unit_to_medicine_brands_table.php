<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('medicine_brands', function (Blueprint $table) {
            $table->string('unit_type')->nullable()->after('wholesale_price');
            $table->integer('conversion')->nullable()->after('unit_type');
            $table->decimal('unit_cost', 8, 2)->nullable()->after('conversion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicine_brands', function (Blueprint $table) {
            $table->dropColumn('unit_type');
            $table->dropColumn('conversion');
            $table->dropColumn('unit_cost');
        });
    }
};
