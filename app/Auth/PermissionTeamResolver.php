<?php

namespace App\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

class PermissionTeamResolver implements PermissionsTeamResolver
{
    /**
     * Spatie instantiates this resolver itself (not via the container), so the
     * explicit-override flag is stored statically to share state with User helpers.
     */
    protected static int|string|null $teamId = null;

    protected static bool $explicit = false;

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        static::$teamId = $id;
        static::$explicit = true;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        if (static::$explicit) {
            return static::$teamId;
        }

        if (! Auth::hasUser()) {
            return null;
        }

        $user = Auth::user();

        if ($user->is_platform_admin) {
            return null;
        }

        return $user->hospital_id;
    }

    public static function hasExplicitTeamId(): bool
    {
        return static::$explicit;
    }

    public static function explicitTeamId(): int|string|null
    {
        return static::$teamId;
    }

    public static function clearExplicitTeamId(): void
    {
        static::$teamId = null;
        static::$explicit = false;
    }
}
