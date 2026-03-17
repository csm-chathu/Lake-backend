<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_charge_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // internal name not shown to customers
            $table->string('label'); // visible label/mask for UI
            $table->decimal('value', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // seed some defaults
        if (Schema::hasTable('doctor_charge_presets')) {
            DB::table('doctor_charge_presets')->insert([
                ['name' => 'standard_300', 'label' => 'Standard', 'value' => 300.00, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'priority_500', 'label' => 'Priority', 'value' => 500.00, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'default_600', 'label' => 'Default', 'value' => 600.00, 'active' => true, 'created_at' => now(), 'updated_at' => now()]
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_charge_presets');
    }
};
