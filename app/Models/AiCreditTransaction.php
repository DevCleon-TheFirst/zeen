<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCreditTransaction extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'amount',
        'balance_after',
        'type',
        'description',
        'tokens_used',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'tokens_used' => 'integer',
            'cost_usd' => 'float',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
