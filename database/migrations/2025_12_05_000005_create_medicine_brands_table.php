<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medicine_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines'); // removed cascadeOnDelete
            $table->string('name', 200)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_brands');
    }
};
