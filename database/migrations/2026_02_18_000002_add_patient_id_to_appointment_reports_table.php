<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_reports', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('appointment_id')->constrained()->nullOnDelete();
        });

        // SQLite doesn't support the MySQL-style UPDATE…JOIN syntax, so use a
        // correlated subquery when running under sqlite. This keeps the same
        // behaviour in all supported drivers.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(
                'UPDATE appointment_reports 
                 SET patient_id = (
                     SELECT patient_id 
                     FROM appointments 
                     WHERE appointments.id = appointment_reports.appointment_id
                 )'
            );
        } else {
            DB::statement(
                'UPDATE appointment_reports AS ar 
                 JOIN appointments AS a 
                   ON a.id = ar.appointment_id 
                 SET ar.patient_id = a.patient_id'
            );
        }
    }

    public function down(): void
    {
        Schema::table('appointment_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('patient_id');
        });
    }
};
