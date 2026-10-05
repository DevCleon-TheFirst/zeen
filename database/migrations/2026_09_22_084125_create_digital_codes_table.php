<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Category: wifi_voucher | booking_pin | ticket | license_key | gift_card | event_code
            $table->string('category')->default('wifi_voucher');

            $table->string('code')->unique(); // e.g., WIFI-8492-KM7X
            $table->string('prefix')->nullable();   // e.g., WIFI, EVENT

            $table->string('status')->default('available'); // available | assigned | redeemed | expired
            $table->unsignedInteger('valid_duration_minutes')->nullable(); // e.g., 1440 = 24 hours
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // bandwidth cap, router profile, room number, etc.

            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'category', 'status']);
            $table->index(['business_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_codes');
    }
};
