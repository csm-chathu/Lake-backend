<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('clinic_settings', 'description')) {
                $table->text('description')->nullable()->after('address');
            }

            if (!Schema::hasColumn('clinic_settings', 'pos_description')) {
                $table->text('pos_description')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            if (Schema::hasColumn('clinic_settings', 'pos_description')) {
                $table->dropColumn('pos_description');
            }

            if (Schema::hasColumn('clinic_settings', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
