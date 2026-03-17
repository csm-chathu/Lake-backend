<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('patients', 'created_at') || ! Schema::hasColumn('patients', 'updated_at')) {
            Schema::table('patients', function (Blueprint $table) {
                if (! Schema::hasColumn('patients', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('notes');
                }
                if (! Schema::hasColumn('patients', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('patients', 'created_at') || Schema::hasColumn('patients', 'updated_at')) {
            Schema::table('patients', function (Blueprint $table) {
                if (Schema::hasColumn('patients', 'updated_at')) {
                    $table->dropColumn('updated_at');
                }
                if (Schema::hasColumn('patients', 'created_at')) {
                    $table->dropColumn('created_at');
                }
            });
        }
    }
};
