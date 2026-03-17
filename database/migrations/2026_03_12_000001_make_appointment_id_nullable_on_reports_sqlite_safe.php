<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointment_reports')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $columns = DB::select("PRAGMA table_info('appointment_reports')");
        $appointmentIdColumn = collect($columns)->firstWhere('name', 'appointment_id');

        if (!$appointmentIdColumn || (int) ($appointmentIdColumn->notnull ?? 0) === 0) {
            return;
        }

        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('appointment_reports_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('report_type', 32);
            $table->string('label')->nullable();
            $table->text('file_url');
            $table->string('file_public_id')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_bytes')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO appointment_reports_tmp (id, appointment_id, patient_id, report_type, label, file_url, file_public_id, mime_type, file_bytes, reported_at, created_at, updated_at)
             SELECT id, appointment_id, patient_id, report_type, label, file_url, file_public_id, mime_type, file_bytes, reported_at, created_at, updated_at
             FROM appointment_reports'
        );

        Schema::drop('appointment_reports');
        Schema::rename('appointment_reports_tmp', 'appointment_reports');

        DB::statement('PRAGMA foreign_keys=ON');
    }

    public function down(): void
    {
        // Intentionally left as no-op to avoid destructive down migration on report data.
    }
};
