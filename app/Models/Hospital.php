<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Support\CoreRoleProvisioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;

class Hospital extends Model
{
    use HasFactory, LogsActivity;

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
     * los globales habilitados por el administrador de plataforma para este hospital, y los
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

    /**
     * Roles que este hospital puede ver/asignar, ya como modelos de Spatie.
     * Incluye roles globales (team_id null) y roles propios del hospital,
     * evitando que aparezcan roles de otros tenants con el mismo nombre.
     *
     * @param list<string> $includeNames
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function visibleRoles(array $includeNames = []): \Illuminate\Support\Collection
    {
        $names = array_values(array_unique([...$this->visibleRoleNames(), ...$includeNames]));

        return Role::query()
            ->whereIn('name', $names)
            ->where(function ($q) {
                $q->whereNull('team_id')
                  ->orWhere('team_id', $this->id);
            })
            ->where('guard_name', 'web')
            ->orderByRaw('CASE WHEN team_id = ? THEN 0 ELSE 1 END', [$this->id])
            ->orderBy('name')
            ->get(['id', 'name', 'team_id'])
            ->keyBy('name')
            ->values();
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

    /**
     * Hospitales piloto (ej. HNSC) nunca deben quedar en `trialing`: el cobro
     * se opera manualmente y su acceso debe mantenerse `active`.
     */
    public function isPilot(): bool
    {
        return in_array($this->slug, config('billing.pilot_hospital_slugs', []), true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['plan', 'subscription_status', 'trial_ends_at', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

            OperatingRoom::seedDefaultFor($hospital);
            SurgeryStatus::seedDefaultsFor($hospital);
            SurgicalRole::seedDefaultsFor($hospital);
            CoreRoleProvisioner::provisionFor($hospital);
        });
    }
}
