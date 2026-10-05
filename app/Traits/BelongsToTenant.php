<?php

namespace App\Traits;

use App\Models\Business;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.business_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->business_id)) {
                $tenantId = TenantContext::id();
                if ($tenantId !== null) {
                    $model->business_id = $tenantId;
                }
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
