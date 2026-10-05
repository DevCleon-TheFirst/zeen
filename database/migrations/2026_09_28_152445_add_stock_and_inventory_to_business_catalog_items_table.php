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
        Schema::table('business_catalog_items', function (Blueprint $table) {
            $table->integer('stock_quantity')->nullable()->after('availability_status')->comment('Available inventory count');
            $table->boolean('track_inventory')->default(true)->after('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'track_inventory']);
        });
    }
};
