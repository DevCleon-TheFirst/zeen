<?php

namespace App\Models;

use App\Enums\ChannelType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerChannelIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'channel',
        'channel_user_id',
        'channel_username',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
