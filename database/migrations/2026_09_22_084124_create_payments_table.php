<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained('business_catalog_items')->nullOnDelete();

            $table->string('gateway')->default('test'); // paystack | stripe | test
            $table->string('currency', 10)->default('NGN');
            $table->unsignedBigInteger('amount_kobo'); // stored in smallest unit (kobo/cents)
            $table->string('description')->nullable();

            $table->string('reference')->unique(); // our internal ref
            $table->string('gateway_reference')->nullable(); // gateway's ref
            $table->string('checkout_url')->nullable();

            $table->string('status')->default('pending'); // pending | completed | failed | refunded
            $table->json('metadata')->nullable(); // product info, voucher specs, etc.
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
