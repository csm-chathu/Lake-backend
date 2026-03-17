<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medicine_brands', function (Blueprint $table) {
            $table->string('batch_number', 120)->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('medicine_brands', function (Blueprint $table) {
            $table->dropColumn('batch_number');
        });
    }
};
