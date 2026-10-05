<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'industry',
        'description',
        'thumbnail',
        'workflow_snapshot',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'workflow_snapshot' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
