<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderSetting extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'provider',
        'api_key',
        'base_url',
        'model',
        'temperature',
        'max_tokens',
        'is_active',
        'last_tested_at',
        'last_test_passed',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_test_passed' => 'boolean',
        ];
    }
}
