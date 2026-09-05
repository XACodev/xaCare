<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role;

class Hospital extends Model
{
    use HasFactory;

    /**
     * Roles del catálogo global que TODO hospital puede asignar siempre, sin
     * necesidad de habilitación explícita. Cualquier rol nuevo que se cree fuera
     * de esta lista nace invisible para todos los hospitales (ver enabled_roles).
     */
    public const CORE_ROLES = ['admin', 'doctor', 'instrumentist', 'circulating'];

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
        'enabled_roles',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'subscription_status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'enabled_roles' => 'array',
        ];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /**
     * Nombres de rol que este hospital puede ver/asignar: los "core" (siempre),
     * los globales habilitados por el super admin para este hospital, y los
     * roles custom creados exclusivamente para este hospital (team_id = hospital_id).
     *
     * @return list<string>
     */
    public function visibleRoleNames(): array
    {
        $customRoleNames = Role::query()
            ->where('team_id', $this->id)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        return array_values(array_unique([
            ...self::CORE_ROLES,
            ...($this->enabled_roles ?? []),
            ...$customRoleNames,
        ]));
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
