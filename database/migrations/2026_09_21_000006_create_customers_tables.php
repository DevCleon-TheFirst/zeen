<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('lead_status')->default('new'); // new|qualified|contacted|negotiating|converted|lost
            $table->unsignedTinyInteger('lead_score')->default(0); // 0-100
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'lead_status']);
        });

        Schema::create('customer_channel_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('channel'); // telegram|whatsapp|messenger|sms
            $table->string('channel_user_id'); // platform-specific ID
            $table->string('channel_username')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'channel_user_id']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_channel_identities');
        Schema::dropIfExists('customers');
    }
};
