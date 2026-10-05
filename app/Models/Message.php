<?php

namespace App\Models;

use App\Events\MessageReceived;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'direction',
        'sender_type',
        'sender_id',
        'channel_message_id',
        'content',
        'media',
        'status',
        'is_batched',
        'is_internal',
    ];

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            try {
                MessageReceived::dispatch($message);
            } catch (\Throwable $e) {
                // Broadcast failure (e.g. Reverb not running locally) should
                // never prevent the message from being ingested and stored.
                Log::warning('MessageReceived broadcast skipped: '.$e->getMessage());
            }
        });
    }

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'is_batched' => 'boolean',
            'is_internal' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }
}
