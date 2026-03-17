<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointment_reports', 'reported_at')) {
            Schema::table('appointment_reports', function (Blueprint $table) {
                $table->timestamp('reported_at')->nullable()->after('file_bytes');
            });

            DB::table('appointment_reports')
                ->whereNull('reported_at')
                ->update(['reported_at' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointment_reports', 'reported_at')) {
            Schema::table('appointment_reports', function (Blueprint $table) {
                $table->dropColumn('reported_at');
            });
        }
    }
};
