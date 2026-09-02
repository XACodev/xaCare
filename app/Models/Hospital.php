<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'features',
        'is_active',
        'subscription_status',
        'trial_ends_at',
        'stripe_id',
        'pm_type',
        'pm_last_four',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'subscription_status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function subscriptionAllowsAccess(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $status = $this->subscription_status ?? SubscriptionStatus::Active;

        if ($status === SubscriptionStatus::Trialing) {
            return $this->trial_ends_at === null || $this->trial_ends_at->isFuture();
        }

        return $status->allowsAccess();
    }

    public function organizationSetting(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HospitalInvitation::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $hospital) {
            OrganizationSetting::create([
                'hospital_id' => $hospital->id,
                'org_name' => $hospital->name,
                'voucher_legend' => 'Por honorarios correspondientes a servicios de instrumentación prestados en procedimientos quirúrgicos.',
            ]);
        });
    }
}
