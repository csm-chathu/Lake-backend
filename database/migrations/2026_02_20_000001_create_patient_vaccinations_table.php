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
        Schema::create('patient_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vaccine_name');
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->date('administered_at')->nullable();
            $table->dateTime('next_due_at')->nullable();
            $table->unsignedSmallInteger('remind_before_days')->default(1);
            $table->string('reminder_status')->default('pending');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->unsignedInteger('reminder_attempts')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['next_due_at', 'reminder_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_vaccinations');
    }
};
