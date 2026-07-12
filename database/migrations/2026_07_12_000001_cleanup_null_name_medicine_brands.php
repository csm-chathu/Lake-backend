<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove batches belonging to null-name brands first
        DB::statement('
            DELETE FROM medicine_brand_batches
            WHERE medicine_brand_id IN (
                SELECT id FROM medicine_brands WHERE name IS NULL OR name = \'\'
            )
        ');

        DB::statement('
            DELETE FROM medicine_brands WHERE name IS NULL OR name = \'\'
        ');
    }

    public function down(): void
    {
        // Irreversible cleanup
    }
};
