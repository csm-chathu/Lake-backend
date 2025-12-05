<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('passbook_number', 32)->unique();
            $table->string('species', 80);
            $table->string('breed', 120)->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->foreignId('owner_id')->constrained('owners')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
