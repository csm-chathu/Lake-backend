<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            $table->boolean('is_damaged')->default(false)->after('line_total');
            $table->timestamp('restocked_at')->nullable()->after('is_damaged');
        });
    }

    public function down(): void
    {
        Schema::table('customer_return_items', function (Blueprint $table) {
            $table->dropColumn(['is_damaged', 'restocked_at']);
        });
    }
};
