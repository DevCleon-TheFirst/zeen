<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'customer_id',
        'conversation_id',
        'payment_id',
        'tracking_code',
        'status',
        'subtotal',
        'shipping_fee',
        'total_amount',
        'currency',
        'shipping_address',
        'customer_name',
        'customer_phone',
        'customer_email',
        'notes',
        'metadata',
        'dispatched_at',
        'delivered_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'metadata' => 'array',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = 'TRK-'.strtoupper(bin2hex(random_bytes(3)));
        } while (self::where('tracking_code', $code)->exists());

        return $code;
    }
}
