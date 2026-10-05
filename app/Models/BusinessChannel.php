<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessChannel extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'channel',
        'label',
        'credentials',
        'webhook_secret',
        'webhook_url',
        'is_active',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelType::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
