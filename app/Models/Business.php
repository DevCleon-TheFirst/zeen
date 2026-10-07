<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'industry',
        'timezone',
        'logo',
        'phone',
        'email',
        'website',
        'description',
        'settings',
        'is_active',
        'plan',
        'ai_credits_balance',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'ai_credits_balance' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_user')->withPivot('role')->withTimestamps();
    }

    public function activeUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(BusinessChannel::class);
    }

    public function aiProviderSetting(): HasOne
    {
        return $this->hasOne(AiProviderSetting::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(AutomationWorkflow::class);
    }

    public function automationWorkflows(): HasMany
    {
        return $this->workflows();
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(BusinessCatalogItem::class);
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

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(AiCreditTransaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
