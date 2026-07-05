<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medicine_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('medicine_categories')->insert([
            ['name' => 'Antibiotics',    'sort_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Antiparasitic',  'sort_order' => 2, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vaccines',       'sort_order' => 3, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Supplements',    'sort_order' => 4, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Surgical',       'sort_order' => 5, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other',          'sort_order' => 6, 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_categories');
    }
};
