<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalCode extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'payment_id',
        'customer_id',
        'category',
        'code',
        'prefix',
        'status',
        'valid_duration_minutes',
        'assigned_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'assigned_at' => 'datetime',
            'expires_at' => 'datetime',
            'valid_duration_minutes' => 'integer',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getHumanValidityAttribute(): string
    {
        if (! $this->valid_duration_minutes) {
            return 'No expiry';
        }

        $minutes = $this->valid_duration_minutes;

        if ($minutes >= 1440 && $minutes % 1440 === 0) {
            $days = $minutes / 1440;

            return $days === 1 ? '24 hours' : "{$days} days";
        }

        if ($minutes >= 60 && $minutes % 60 === 0) {
            $hours = $minutes / 60;

            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$minutes} minutes";
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
