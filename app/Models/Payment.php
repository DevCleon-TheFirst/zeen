<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'customer_id',
        'conversation_id',
        'catalog_item_id',
        'gateway',
        'currency',
        'amount_kobo',
        'description',
        'reference',
        'gateway_reference',
        'checkout_url',
        'status',
        'metadata',
        'paid_at',
    ];

    protected $appends = [
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(BusinessCatalogItem::class, 'catalog_item_id');
    }

    public function digitalCode(): HasOne
    {
        return $this->hasOne(DigitalCode::class);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    /** Amount in major currency unit (naira, dollars, etc.) */
    public function getAmountAttribute(): float
    {
        return $this->amount_kobo / 100;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
