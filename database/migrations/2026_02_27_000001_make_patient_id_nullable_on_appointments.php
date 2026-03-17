<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite doesn't support dropping or re-adding foreign keys in-place. The
        // schema change is primarily needed for MySQL/Postgres. For SQLite we can
        // simply make the column nullable; the foreign key constraint isn't
        // enforced in the same way anyway, so abstracting it away avoids errors.
        if (DB::getDriverName() === 'sqlite') {
            // SQLite can’t alter an existing column’s nullability without the
            // doctrine/dbal package. For simple local/test setups we just skip
            // this migration on sqlite; appointments will keep the NOT NULL
            // constraint. Install `doctrine/dbal` or use MySQL/Postgres if you
            // need the column to actually become nullable.
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            // drop existing foreign key so we can alter the column
            $table->dropForeign(['patient_id']);
            // make column nullable
            $table->unsignedBigInteger('patient_id')->nullable()->change();
            // re-create foreign key constraint with cascade on delete
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('patient_id')->nullable(false)->change();
            });
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->unsignedBigInteger('patient_id')->nullable(false)->change();
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
        });
    }
};
