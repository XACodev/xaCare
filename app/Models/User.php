<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Auth\PermissionTeamResolver;
use App\Models\Concerns\BelongsToTenant;
use App\Modules\QxLog\Models\SurgicalAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable {
        HasRoles::assignRole as protected assignSpatieRole;
        HasRoles::syncRoles as protected syncSpatieRoles;
        HasRoles::removeRole as protected removeSpatieRole;
        HasRoles::hasRole as protected hasSpatieRole;
        HasRoles::givePermissionTo as protected giveSpatiePermissionTo;
        HasRoles::syncPermissions as protected syncSpatiePermissions;
        HasRoles::hasPermissionTo as protected hasSpatiePermissionTo;
        HasRoles::checkPermissionTo as protected checkSpatiePermissionTo;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'hospital_id',
        'email',
        'password',
        'username',
        'role',
        'is_platform_admin',
        'use_pay_scheme',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'role' => 'string',
            'use_pay_scheme' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * El super admin sí puede crear/editar usuarios y administradores de cualquier hospital
     * vía /users (gestión ya protegida por su propio middleware `superadmin`).
     */
    public static function allowsPlatformAdminWrites(): bool
    {
        return true;
    }

    protected static function booted(): void
    {
        static::saving(function ($user) {
            if (! $user->is_platform_admin && is_null($user->hospital_id)) {
                abort(422, 'Los usuarios de hospital deben tener un hospital asignado.');
            }
        });
    }

    /**
     * Asignaciones quirurgicas del usuario como beneficiario.
     */
    public function assignments()
    {
        return $this->hasMany(SurgicalAssignment::class);
    }

    public function assignRole(...$roles): static
    {
        $this->withHospitalPermissionsTeam(fn () => $this->assignSpatieRole(...$roles));

        return $this;
    }

    public function syncRoles(...$roles): static
    {
        $this->withHospitalPermissionsTeam(fn () => $this->syncSpatieRoles(...$roles));

        return $this;
    }

    public function removeRole(...$role): static
    {
        $this->withHospitalPermissionsTeam(fn () => $this->removeSpatieRole(...$role));

        return $this;
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        $this->setRelation('roles', $this->roles()->get());

        return $this->hasSpatieRole($roles, $guard);
    }

    public function givePermissionTo(...$permissions): static
    {
        $this->withHospitalPermissionsTeam(fn () => $this->giveSpatiePermissionTo(...$permissions));

        return $this;
    }

    public function syncPermissions(...$permissions): static
    {
        $this->withHospitalPermissionsTeam(fn () => $this->syncSpatiePermissions(...$permissions));

        return $this;
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $this->setRelation('roles', $this->roles()->get());
        $this->setRelation('permissions', $this->permissions()->get());

        return $this->hasSpatiePermissionTo($permission, $guardName);
    }

    public function checkPermissionTo($permission, $guardName = null): bool
    {
        try {
            return $this->hasPermissionTo($permission, $guardName);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    public function getRoleNames(): Collection
    {
        $this->setRelation('roles', $this->roles()->get());

        return $this->roles->pluck('name');
    }

    public function roles(): BelongsToMany
    {
        $registrar = app(PermissionRegistrar::class);

        $relation = $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            $registrar->pivotRole
        );

        if (! $registrar->teams) {
            return $relation;
        }

        $teamsKey = $registrar->teamsKey;
        $relation->withPivot($teamsKey);
        $teamField = config('permission.table_names.roles').'.'.$teamsKey;

        $teamId = PermissionTeamResolver::hasExplicitTeamId()
            ? PermissionTeamResolver::explicitTeamId()
            : $this->hospital_id;

        if (! $this->exists && ! PermissionTeamResolver::hasExplicitTeamId()) {
            return $relation;
        }

        return $relation->wherePivot($teamsKey, $teamId)
            ->where(fn ($q) => $q->whereNull($teamField)->orWhere($teamField, $teamId));
    }

    public function permissions(): BelongsToMany
    {
        $registrar = app(PermissionRegistrar::class);

        $relation = $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key'),
            $registrar->pivotPermission
        );

        if (! $registrar->teams) {
            return $relation;
        }

        $teamsKey = $registrar->teamsKey;
        $relation->withPivot($teamsKey);

        $teamId = PermissionTeamResolver::hasExplicitTeamId()
            ? PermissionTeamResolver::explicitTeamId()
            : $this->hospital_id;

        if (! $this->exists && ! PermissionTeamResolver::hasExplicitTeamId()) {
            return $relation;
        }

        return $relation->wherePivot($teamsKey, $teamId);
    }

    protected function withHospitalPermissionsTeam(callable $callback): mixed
    {
        if (! config('permission.teams')) {
            return $callback();
        }

        $wasExplicit = PermissionTeamResolver::hasExplicitTeamId();
        $previousTeamId = PermissionTeamResolver::explicitTeamId();

        setPermissionsTeamId($this->hospital_id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $callback();
        } finally {
            if ($wasExplicit) {
                setPermissionsTeamId($previousTeamId);
            } else {
                PermissionTeamResolver::clearExplicitTeamId();
            }

            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
