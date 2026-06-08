<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return; // already handled by the earlier migration on MySQL/Postgres
        }

        // doctrine/dbal is available, so ->change() works on SQLite via table recreation.
        // Disable foreign key enforcement during the operation.
        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('patient_id')->nullable()->change();
            });
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');
        try {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('patient_id')->nullable(false)->change();
            });
        } finally {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }
};
