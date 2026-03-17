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
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'user_type')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['doctor', 'cashier', 'pos_admin'])->default('doctor')->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'user_type')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
