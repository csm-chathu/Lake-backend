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
        // SAFELY convert 'type' column to JSON without dropping or deleting any rows
        if (Schema::hasColumn('medicines', 'type')) {
            // If the column is not already JSON, alter it to JSON type (SQLite: no alter, just keep as TEXT)
            // For MySQL, you could use: $table->json('type')->change();
            // For SQLite, skip type change, just update the data format
            $all = DB::table('medicines')->select('id', 'type')->get();
            foreach ($all as $row) {
                $typeValue = $row->type;
                $jsonValue = null;
                if (is_null($typeValue)) {
                    $jsonValue = json_encode([]);
                } elseif (in_array($typeValue, ['medicine', 'item', 'service'])) {
                    $jsonValue = json_encode([$typeValue]);
                } else {
                    $jsonValue = json_encode([$typeValue]);
                }
                DB::table('medicines')->where('id', $row->id)->update(['type' => $jsonValue]);
            }
        }
        // No columns are dropped, no rows are deleted. This is safe for all related tables.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Add back the old string column
        Schema::table('medicines', function (Blueprint $table) {
            $table->string('type', 32)->nullable()->after('description');
        });

        // 2. Convert JSON array back to string (first value or null)
        $all = DB::table('medicines')->select('id', 'type')->get();
        foreach ($all as $row) {
            $typeArr = json_decode($row->type, true);
            $stringValue = is_array($typeArr) && count($typeArr) > 0 ? $typeArr[0] : null;
            DB::table('medicines')->where('id', $row->id)->update(['type' => $stringValue]);
        }

        // 3. Drop the JSON column
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
