<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Enums\ConversationState;
use App\Services\Automation\AutomationTriggerDispatcher;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'business_channel_id',
        'channel',
        'status',
        'handler',
        'assigned_to',
        'priority',
        'ai_confidence_score',
        'escalation_reason',
        'last_message_at',
        'human_last_replied_at',
        'resolved_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelType::class,
            'status' => ConversationState::class,
            'ai_confidence_score' => 'integer',
            'last_message_at' => 'datetime',
            'human_last_replied_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function businessChannel(): BelongsTo
    {
        return $this->belongsTo(BusinessChannel::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class);
    }

    public function escalate(string $reason): void
    {
        $this->update([
            'status' => ConversationState::Escalated,
            'handler' => 'human',
            'escalation_reason' => $reason,
        ]);
    }

    public function resolve(): void
    {
        $this->update([
            'status' => ConversationState::Resolved,
            'resolved_at' => now(),
        ]);

        // Fire automation workflows listening for conversation_resolved
        AutomationTriggerDispatcher::dispatch('conversation_resolved', [
            'conversation_id' => $this->id,
            'customer_id' => $this->customer_id,
            'channel' => $this->channel->value,
        ], $this->business);
    }

    public function handoverToHuman(?User $user = null): void
    {
        $this->update([
            'status' => ConversationState::HumanHandling,
            'handler' => 'human',
            'assigned_to' => $user?->id ?? $this->assigned_to,
        ]);
    }

    public function handoverToAi(): void
    {
        $this->update([
            'status' => ConversationState::AiHandling,
            'handler' => 'ai',
        ]);
    }
}
