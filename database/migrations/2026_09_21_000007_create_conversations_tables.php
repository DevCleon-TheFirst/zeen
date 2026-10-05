<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('business_channel_id')->constrained('business_channels')->cascadeOnDelete();
            $table->string('channel'); // telegram|whatsapp|messenger|sms (denormalised for speed)
            $table->string('status')->default('new');
            // new|ai_handling|human_handling|waiting_for_customer|waiting_for_business|escalated|resolved
            $table->string('handler')->default('ai'); // ai|human
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority')->default('normal'); // normal|high|urgent
            $table->unsignedTinyInteger('ai_confidence_score')->nullable(); // 0-100
            $table->text('escalation_reason')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable(); // flexible extra data per channel
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'handler']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('direction'); // inbound|outbound
            $table->string('sender_type'); // customer|ai|human
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete(); // set when human
            $table->string('channel_message_id')->nullable(); // platform's own message ID
            $table->text('content')->nullable();
            $table->json('media')->nullable(); // images, documents, audio
            $table->string('status')->default('sent'); // sent|delivered|read|failed
            $table->boolean('is_batched')->default(false); // part of a debounced batch
            $table->timestamps();

            $table->unique('channel_message_id'); // idempotency
            $table->index(['conversation_id', 'created_at']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
