<?php

namespace App\Models;

use App\Services\Automation\AutomationTriggerDispatcher;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'lead_status',
        'lead_score',
        'tags',
        'notes',
        'last_contacted_at',
        'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'lead_score' => 'integer',
            'tags' => 'array',
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Customer $customer) {
            if ($customer->wasChanged('tags') && $customer->business) {
                $oldTags = (array) $customer->getOriginal('tags');
                $newTags = (array) $customer->tags;
                $added = array_diff($newTags, $oldTags);

                foreach ($added as $tag) {
                    AutomationTriggerDispatcher::dispatch('tag_added', [
                        'customer_id' => $customer->id,
                        'tag' => $tag,
                    ], $customer->business);
                }
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(CustomerChannelIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function digitalCodes(): HasMany
    {
        return $this->hasMany(DigitalCode::class);
    }
}
