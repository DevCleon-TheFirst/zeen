<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationWorkflow extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'trigger_type',
        'trigger_config',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(AutomationNode::class, 'workflow_id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(AutomationEdge::class, 'workflow_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class, 'workflow_id');
    }
}
