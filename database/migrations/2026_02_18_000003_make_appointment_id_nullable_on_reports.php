<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If we're running on SQLite, just mark this migration as a no-op.
        // The column is created NOT NULL in the previous migration and for test
        // data it's usually fine to leave it that way; attempting to drop or
        // recreate the foreign key will raise an error, and altering column
        // definitions with `change()` is not reliable. Skipping here keeps the
        // sqlite environment simple and avoids the BadMethodCallException.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointment_reports', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
        });

        DB::statement('ALTER TABLE appointment_reports MODIFY appointment_id BIGINT UNSIGNED NULL');

        Schema::table('appointment_reports', function (Blueprint $table) {
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('appointment_reports', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
        });

        DB::table('appointment_reports')->whereNull('appointment_id')->delete();

        DB::statement('ALTER TABLE appointment_reports MODIFY appointment_id BIGINT UNSIGNED NOT NULL');

        Schema::table('appointment_reports', function (Blueprint $table) {
            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
        });
    }
};
