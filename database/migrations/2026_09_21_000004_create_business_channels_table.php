<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('channel'); // telegram|whatsapp|messenger|sms
            $table->string('label')->nullable(); // friendly name e.g. "Main WhatsApp"
            $table->text('credentials'); // encrypted: bot tokens, page tokens, phone IDs
            $table->string('webhook_secret')->nullable();
            $table->string('webhook_url')->nullable(); // computed/stored for display
            $table->boolean('is_active')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_channels');
    }
};
