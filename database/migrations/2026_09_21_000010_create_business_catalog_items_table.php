<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('currency', 10)->default('NGN');
            $table->string('category')->nullable();
            $table->string('availability_status')->default('available'); // available|unavailable|coming_soon
            $table->json('attributes')->nullable(); // flexible: {bedrooms:3, location:'Lekki'} or {duration:'6mo'} etc.
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'category', 'availability_status'], 'bci_biz_cat_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_catalog_items');
    }
};
