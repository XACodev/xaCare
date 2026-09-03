<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToTenant;
use App\Modules\QxLog\Models\SurgicalAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

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
