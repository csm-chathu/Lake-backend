<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE suppliers ENGINE=InnoDB');
            DB::statement('ALTER TABLE medicine_brand_batches ENGINE=InnoDB');
        }

        Schema::table('medicine_brand_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_brand_batches', 'barcode')) {
                $table->string('barcode')->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('medicine_brand_batches', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('barcode');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE medicine_brand_batches ADD CONSTRAINT medicine_brand_batches_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
            } catch (\Throwable $e) {
                // ignore if FK already exists or cannot be created in this environment
            }
        } else {
            Schema::table('medicine_brand_batches', function (Blueprint $table) {
                try {
                    $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
                } catch (\Throwable $e) {
                    // ignore if FK already exists
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicine_brand_batches', function (Blueprint $table) {
            try {
                $table->dropForeign(['supplier_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            $drop = [];
            foreach (['barcode', 'supplier_id'] as $column) {
                if (Schema::hasColumn('medicine_brand_batches', $column)) {
                    $drop[] = $column;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
