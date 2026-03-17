<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE suppliers ENGINE=InnoDB');
            DB::statement('ALTER TABLE medicine_brands ENGINE=InnoDB');
        }

        Schema::table('medicine_brands', function (Blueprint $table) {
            if (!Schema::hasColumn('medicine_brands', 'stock')) {
                $table->integer('stock')->nullable()->after('price');
            }
            if (!Schema::hasColumn('medicine_brands', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('stock');
            }
            if (!Schema::hasColumn('medicine_brands', 'barcode')) {
                $table->string('barcode', 100)->nullable()->after('expiry_date');
            }
            if (!Schema::hasColumn('medicine_brands', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('barcode');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE medicine_brands ADD CONSTRAINT medicine_brands_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
            } catch (\Throwable $e) {
                // ignore if FK already exists or cannot be created in this environment
            }
        } else {
            Schema::table('medicine_brands', function (Blueprint $table) {
                try {
                    $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
                } catch (\Throwable $e) {
                    // ignore if FK already exists
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('medicine_brands', function (Blueprint $table) {
            try {
                $table->dropForeign(['supplier_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            $drop = [];
            foreach (['stock', 'expiry_date', 'barcode', 'supplier_id'] as $column) {
                if (Schema::hasColumn('medicine_brands', $column)) {
                    $drop[] = $column;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
