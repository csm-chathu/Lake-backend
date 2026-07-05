<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('label');          // visible label e.g. "Medicine & Item"
            $table->string('value');          // comma-separated keys e.g. "medicine,item"
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed defaults
        DB::table('item_types')->insert([
            ['label' => 'Medicine',        'value' => 'medicine',      'sort_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Item',            'value' => 'item',          'sort_order' => 2, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Medicine & Item', 'value' => 'medicine,item', 'sort_order' => 3, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['label' => 'Service',         'value' => 'service',       'sort_order' => 4, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('item_types');
    }
};
