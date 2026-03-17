<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_medicines', function (Blueprint $table) {
            // Change quantity to decimal so fractional amounts like 0.5 or 0.2 can be stored.
            // Use the same precision as unit_price; quantities are unlikely to be huge.
            $table->decimal('quantity', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_medicines', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(0)->change();
        });
    }
};
