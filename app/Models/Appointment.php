<?php

namespace App\Models;

use App\Services\Automation\AutomationTriggerDispatcher;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'customer_id',
        'assigned_to',
        'conversation_id',
        'catalog_item_id',
        'title',
        'description',
        'location',
        'scheduled_at',
        'duration_minutes',
        'status',
        'cancellation_reason',
        'reminder_sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'reminder_sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Appointment $appointment) {
            $business = $appointment->business ?? $appointment->customer?->business;
            if ($business) {
                AutomationTriggerDispatcher::dispatch('appointment_created', [
                    'appointment_id' => $appointment->id,
                    'customer_id' => $appointment->customer_id,
                    'conversation_id' => $appointment->conversation_id,
                    'scheduled_at' => $appointment->scheduled_at?->toIso8601String(),
                    'title' => $appointment->title,
                ], $business);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(BusinessCatalogItem::class, 'catalog_item_id');
    }
}
