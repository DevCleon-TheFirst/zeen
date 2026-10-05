<?php

namespace App\Models;

use App\Services\Automation\AutomationTriggerDispatcher;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'customer_id',
        'assigned_to',
        'stage',
        'score',
        'source',
        'product_interest',
        'notes',
        'estimated_value',
        'currency',
        'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'estimated_value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Lead $lead) {
            if ($lead->wasChanged('stage')) {
                $business = $lead->business ?? $lead->customer?->business;
                if ($business) {
                    AutomationTriggerDispatcher::dispatch('lead_stage_changed', [
                        'lead_id' => $lead->id,
                        'customer_id' => $lead->customer_id,
                        'old_stage' => $lead->getOriginal('stage'),
                        'new_stage' => $lead->stage,
                    ], $business);
                }
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
}
