# Panel de Administrador de plataforma — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename `superadmin`/`is_super_admin` to `platform-admin`/`is_platform_admin` throughout the codebase, and build a dedicated panel (`/platform/*`) for the Administrador de plataforma with its own layout/navigation, a metrics dashboard, an activity feed, and an invitation-based flow to create new platform admin accounts.

**Architecture:** Laravel 12 + Livewire Volt (single-file components) + Flux UI. Follows the existing `HospitalInvitation` pattern (SHA-256 token, one-time link) for the new `PlatformAdminInvitation`. Follows the existing route-prefix/middleware-alias conventions already in `routes/web.php` and `bootstrap/app.php`. No new dependencies.

**Tech Stack:** PHP 8.4, Laravel 12, Livewire Volt, Pest (tests), Spatie Permission, Spatie Activitylog (already installed).

**Spec:** `docs/superpowers/specs/2026-09-02-panel-administrador-plataforma-design.md`

## Global Constraints

- Never mention Claude/Anthropic in commit messages or PRs.
- Never lose production data — the column rename uses `renameColumn` (non-destructive); no other destructive migrations in this plan.
- Production (Laravel Cloud) only ever runs `php artisan migrate --force` at deploy — no custom artisan commands for data migration.
- Do not merge `develop` → `main` — this plan targets `develop` only.
- Every task must leave the full Pest suite green before its commit, except where a task's own description explicitly says otherwise (schema-only steps verified by other means).
- Local dev: PHP via Herd (`php`, `composer` on PATH), site at `https://xacare.test`, DB is SQLite (`database/database.sqlite`).

---

## Part A — Rename `superadmin` → `platform-admin`

### Task 1: Migration — rename `is_super_admin` column

**Files:**
- Create: `database/migrations/2026_09_02_190000_rename_is_super_admin_to_is_platform_admin.php`

**Interfaces:**
- Produces: `users.is_platform_admin` column (boolean, same position/default as the old `is_super_admin`). Every later task in this plan reads/writes this column name, never the old one (except the historical migration that originally created it, which stays untouched).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('is_super_admin', 'is_platform_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('is_platform_admin', 'is_super_admin');
        });
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate`
Expected: `2026_09_02_190000_rename_is_super_admin_to_is_platform_admin ... DONE`

- [ ] **Step 3: Verify the column was renamed without losing data**

Run: `php artisan tinker --execute="dump(Schema::hasColumn('users', 'is_platform_admin'), Schema::hasColumn('users', 'is_super_admin'), App\Models\User::withoutGlobalScopes()->count());"`
Expected: `true`, `false`, and the same user count as before the migration (the app will not boot correctly yet — `User::$fillable`/`casts()` and every query still say `is_super_admin`, this step only proves the schema change itself is safe; full app correctness comes back at the end of Task 3).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_09_02_190000_rename_is_super_admin_to_is_platform_admin.php
git commit -m "feat: rename users.is_super_admin column to is_platform_admin"
```

---

### Task 2: Rename the middleware and its alias

**Files:**
- Create: `app/Http/Middleware/PlatformAdminOnly.php`
- Delete: `app/Http/Middleware/SuperAdminOnly.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php:79` (only the alias string on this one line — the full `/platform` route restructuring happens in Task 5)

**Interfaces:**
- Consumes: nothing new.
- Produces: middleware alias `platform-admin` (registered in `bootstrap/app.php`), class `App\Http\Middleware\PlatformAdminOnly`. Every route group gated to the platform admin from here on uses the alias `platform-admin`, never `superadmin`.

- [ ] **Step 1: Create the renamed middleware class**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformAdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless((bool) $user && (bool) $user->is_platform_admin, 403);

        return $next($request);
    }
}
```

- [ ] **Step 2: Delete the old file**

```bash
git rm app/Http/Middleware/SuperAdminOnly.php
```

- [ ] **Step 3: Update `bootstrap/app.php`**

In `bootstrap/app.php`, change the import and the alias entry:

```php
use App\Http\Middleware\PlatformAdminOnly;
```

(replacing `use App\Http\Middleware\SuperAdminOnly;`), and:

```php
'platform-admin' => PlatformAdminOnly::class,
```

(replacing `'superadmin' => SuperAdminOnly::class,`).

- [ ] **Step 4: Fix the one route group that used the old alias, so the app still boots**

In `routes/web.php`, change:

```php
Route::middleware(['auth', 'superadmin'])->group(function () {
```

to:

```php
Route::middleware(['auth', 'platform-admin'])->group(function () {
```

(This whole group gets replaced by the `/platform` prefix group in Task 5 — this step only keeps the app bootable in the meantime.)

- [ ] **Step 5: Verify the app boots and routes resolve**

Run: `php artisan route:list --name=hospitals`
Expected: no error, `hospitals.index`/`hospitals.create`/`hospitals.edit` still listed (unchanged at this point — only the middleware alias changed, not the group structure).

Run: `php artisan config:clear && curl -s -o /dev/null -w "%{http_code}\n" https://xacare.test/login`
Expected: `200`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/PlatformAdminOnly.php bootstrap/app.php routes/web.php
git commit -m "feat: rename superadmin middleware alias to platform-admin"
```

---

### Task 3: Rename `is_super_admin` identifiers across application and test code

This is one atomic rename from the test suite's point of view (a column that no longer exists breaks every test that references it by old name), so production code and test code are renamed together in this single task, verified once at the end by the full suite. Splitting it further would create fake "green" checkpoints that don't reflect real reviewability — a reviewer accepts or rejects this whole rename as one unit.

**Files:**
- Modify (production code): `app/Console/Commands/BackfillPatients.php`, `app/Console/Commands/CreateSA.php`, `app/Http/Middleware/AdminAuth.php`, `app/Http/Middleware/EnsureHospitalFeature.php`, `app/Http/Middleware/EnsureHospitalSubscribed.php`, `app/Models/Concerns/BelongsToTenant.php`, `app/Models/HospitalInvitation.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `database/seeders/QxLogInitialAdminsSeeder.php`, `database/seeders/QxLogTestSeeder.php`, `resources/views/livewire/admissions/create.blade.php`, `resources/views/livewire/hospital-invitations/accept.blade.php`, `resources/views/livewire/hospitals/create.blade.php`, `resources/views/livewire/hospitals/edit.blade.php`, `resources/views/livewire/hospitals/index.blade.php`, `resources/views/livewire/patients/create.blade.php`, `resources/views/livewire/patients/index.blade.php`, `resources/views/livewire/payouts/create.blade.php`, `resources/views/livewire/pricing/instrumentist.blade.php`, `resources/views/livewire/procedures/create.blade.php`, `resources/views/livewire/procedures/index.blade.php`, `resources/views/livewire/users/create.blade.php`, `resources/views/livewire/users/edit.blade.php`, `resources/views/livewire/users/index.blade.php`
- Modify (tests, plain rename in place): `tests/Feature/Billing/InsuranceGateTest.php`, `tests/Feature/Http/AdminAuthMiddlewareTest.php`, `tests/Feature/Livewire/Hospitals/BillingTest.php`, `tests/Feature/Livewire/Hospitals/CreateTest.php`, `tests/Feature/Livewire/Hospitals/IndexTest.php`, `tests/Feature/Livewire/Hospitals/InvitationTest.php`, `tests/Feature/Livewire/Hospitals/RoleVisibilityTest.php`, `tests/Feature/Livewire/Hospitals/StaffTest.php`, `tests/Feature/Livewire/Patients/IndexTest.php`, `tests/Feature/Livewire/Procedures/IndexTest.php`, `tests/Feature/Livewire/Users/CreateTest.php`, `tests/Feature/Livewire/Users/EditTest.php`, `tests/Feature/Tenancy/OrganizationSettingTenantTest.php`, `tests/Feature/Tenancy/TenantScopeTest.php`, `tests/Feature/Tenancy/UserTenantScopeTest.php`
- Rename (git mv + content rename): `tests/Feature/Routes/SuperAdminReadOnlyTest.php` → `tests/Feature/Routes/PlatformAdminReadOnlyTest.php`; `tests/Feature/Tenancy/SuperAdminReadOnlyFase1Test.php` → `tests/Feature/Tenancy/PlatformAdminReadOnlyFase1Test.php`
- Explicitly NOT touched: `database/migrations/2026_01_07_165455_add_qxlog_fields_to_users_table.php` (historical migration — it correctly says `is_super_admin` because that's what it added at the time; never edit an already-applied migration), `resources/views/components/layouts/app/sidebar.blade.php` and `resources/views/components/layouts/app/header.blade.php` (handled in Task 6 — the platform-admin branches in these files get *removed*, not renamed)

**Interfaces:**
- Produces: `is_platform_admin` used everywhere in place of `is_super_admin`; `User::allowsPlatformAdminWrites()` (was `allowsSuperAdminWrites()`); `BelongsToTenant::abortIfPlatformAdminWriteBlocked()` (was `abortIfSuperAdminWriteBlocked()`); test helpers `makePlatformAdmin()` (was `makeSuperAdmin()`) and `makeFase1PlatformAdmin()` (was `makeFase1SuperAdmin()`).

- [ ] **Step 1: Bulk-rename the identifiers across the file list above**

```bash
FILES=(
  app/Console/Commands/BackfillPatients.php
  app/Console/Commands/CreateSA.php
  app/Http/Middleware/AdminAuth.php
  app/Http/Middleware/EnsureHospitalFeature.php
  app/Http/Middleware/EnsureHospitalSubscribed.php
  app/Models/Concerns/BelongsToTenant.php
  app/Models/HospitalInvitation.php
  app/Models/User.php
  app/Providers/AppServiceProvider.php
  database/seeders/QxLogInitialAdminsSeeder.php
  database/seeders/QxLogTestSeeder.php
  resources/views/livewire/admissions/create.blade.php
  resources/views/livewire/hospital-invitations/accept.blade.php
  resources/views/livewire/hospitals/create.blade.php
  resources/views/livewire/hospitals/edit.blade.php
  resources/views/livewire/hospitals/index.blade.php
  resources/views/livewire/patients/create.blade.php
  resources/views/livewire/patients/index.blade.php
  resources/views/livewire/payouts/create.blade.php
  resources/views/livewire/pricing/instrumentist.blade.php
  resources/views/livewire/procedures/create.blade.php
  resources/views/livewire/procedures/index.blade.php
  resources/views/livewire/users/create.blade.php
  resources/views/livewire/users/edit.blade.php
  resources/views/livewire/users/index.blade.php
  tests/Feature/Billing/InsuranceGateTest.php
  tests/Feature/Http/AdminAuthMiddlewareTest.php
  tests/Feature/Livewire/Hospitals/BillingTest.php
  tests/Feature/Livewire/Hospitals/CreateTest.php
  tests/Feature/Livewire/Hospitals/IndexTest.php
  tests/Feature/Livewire/Hospitals/InvitationTest.php
  tests/Feature/Livewire/Hospitals/RoleVisibilityTest.php
  tests/Feature/Livewire/Hospitals/StaffTest.php
  tests/Feature/Livewire/Patients/IndexTest.php
  tests/Feature/Livewire/Procedures/IndexTest.php
  tests/Feature/Livewire/Users/CreateTest.php
  tests/Feature/Livewire/Users/EditTest.php
  tests/Feature/Tenancy/OrganizationSettingTenantTest.php
  tests/Feature/Tenancy/TenantScopeTest.php
  tests/Feature/Tenancy/UserTenantScopeTest.php
)

for f in "${FILES[@]}"; do
  sed -i '' \
    -e 's/is_super_admin/is_platform_admin/g' \
    -e 's/allowsSuperAdminWrites/allowsPlatformAdminWrites/g' \
    -e 's/abortIfSuperAdminWriteBlocked/abortIfPlatformAdminWriteBlocked/g' \
    "$f"
done
```

(`sed -i ''` is the macOS/BSD form used on this machine; if run on GNU sed, drop the empty `''` argument.)

- [ ] **Step 2: Rename the two test files (path + internal helper names)**

```bash
git mv tests/Feature/Routes/SuperAdminReadOnlyTest.php tests/Feature/Routes/PlatformAdminReadOnlyTest.php
git mv tests/Feature/Tenancy/SuperAdminReadOnlyFase1Test.php tests/Feature/Tenancy/PlatformAdminReadOnlyFase1Test.php

sed -i '' \
  -e 's/is_super_admin/is_platform_admin/g' \
  -e 's/makeSuperAdmin/makePlatformAdmin/g' \
  tests/Feature/Routes/PlatformAdminReadOnlyTest.php

sed -i '' \
  -e 's/is_super_admin/is_platform_admin/g' \
  -e 's/makeFase1SuperAdmin/makeFase1PlatformAdmin/g' \
  tests/Feature/Tenancy/PlatformAdminReadOnlyFase1Test.php
```

- [ ] **Step 3: Fix the one user-facing message that still says "Super admin" in Spanish**

In `app/Models/Concerns/BelongsToTenant.php`, the abort message inside `abortIfPlatformAdminWriteBlocked()` currently reads `'Super admin es de solo lectura sobre datos operativos de hospitales.'`. Change it to match the wording already used elsewhere in the app (e.g. `procedures/create.blade.php`):

```php
abort(403, 'Administrador de plataforma es de solo lectura sobre datos operativos de hospitales.');
```

- [ ] **Step 4: Verify no stray old identifiers remain outside the historical migration**

Run:
```bash
grep -rn "is_super_admin\|SuperAdminOnly\|allowsSuperAdminWrites\|abortIfSuperAdminWriteBlocked\|makeSuperAdmin\|makeFase1SuperAdmin" --include="*.php" . | grep -v vendor | grep -v node_modules | grep -v "2026_01_07_165455_add_qxlog_fields_to_users_table.php"
```
Expected: no output (the only remaining hits, if any, should be `is_super_admin` inside the historical migration file, which this grep already excludes — if the historical migration shows up anyway, that's fine and expected, it must never be edited).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all tests pass (183+ tests, same count as before this task — pure rename, no behavior change).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor: rename is_super_admin identifiers to is_platform_admin across app and tests"
```

---

## Part B — Panel de Administrador de plataforma

### Task 4: `PlatformAdminInvitation` model

Mirrors `App\Models\HospitalInvitation` but is not tenant-scoped (no `hospital_id`, no `BelongsToTenant`) — it is a platform-level invitation, not hospital data.

**Files:**
- Create: `database/migrations/2026_09_02_200000_create_platform_admin_invitations_table.php`
- Create: `app/Models/PlatformAdminInvitation.php`
- Create: `database/factories/PlatformAdminInvitationFactory.php`
- Test: `tests/Unit/Models/PlatformAdminInvitationTest.php`

**Interfaces:**
- Produces: `PlatformAdminInvitation::generateFor(int $invitedBy, ?string $note = null, int $expiresInDays = 7): array{0: PlatformAdminInvitation, 1: string}`, `PlatformAdminInvitation::findValidByPlainTextToken(string $plainTextToken): ?self`, `->status(): string` (`'pending'|'accepted'|'expired'`), `->isExpired(): bool`, `->isAccepted(): bool`, `->isPending(): bool`. Task 9 (the invite/accept UI) consumes exactly these.

- [ ] **Step 1: Write the failing model tests**

```php
<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;

test('generateFor creates an invitation and returns the one-time plaintext token', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    [$invitation, $plainTextToken] = PlatformAdminInvitation::generateFor($inviter->id, 'Nueva admin de soporte');

    expect($invitation->exists)->toBeTrue()
        ->and($invitation->note)->toBe('Nueva admin de soporte')
        ->and($invitation->invited_by)->toBe($inviter->id)
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and(strlen($plainTextToken))->toBe(64)
        ->and($invitation->token)->toBe(hash('sha256', $plainTextToken))
        ->and($invitation->token)->not->toBe($plainTextToken);
});

test('findValidByPlainTextToken finds a pending, unexpired invitation', function () {
    [$invitation, $plainTextToken] = PlatformAdminInvitation::generateFor(1);

    $found = PlatformAdminInvitation::findValidByPlainTextToken($plainTextToken);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($invitation->id);
});

test('findValidByPlainTextToken returns null for a token that never existed', function () {
    expect(PlatformAdminInvitation::findValidByPlainTextToken('never-existed'))->toBeNull();
});

test('findValidByPlainTextToken returns null for an expired invitation', function () {
    $invitation = PlatformAdminInvitation::factory()->expired()->create();
    $plainTextToken = 'expired-plain-token';
    $invitation->update(['token' => hash('sha256', $plainTextToken)]);

    expect(PlatformAdminInvitation::findValidByPlainTextToken($plainTextToken))->toBeNull();
});

test('findValidByPlainTextToken returns null for an already accepted invitation', function () {
    $invitation = PlatformAdminInvitation::factory()->accepted()->create();
    $plainTextToken = 'used-plain-token';
    $invitation->update(['token' => hash('sha256', $plainTextToken)]);

    expect(PlatformAdminInvitation::findValidByPlainTextToken($plainTextToken))->toBeNull();
});

test('status reflects pending, accepted and expired states', function () {
    $pending = PlatformAdminInvitation::factory()->create();
    $accepted = PlatformAdminInvitation::factory()->accepted()->create();
    $expired = PlatformAdminInvitation::factory()->expired()->create();

    expect($pending->status())->toBe('pending')
        ->and($accepted->status())->toBe('accepted')
        ->and($expired->status())->toBe('expired');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PlatformAdminInvitationTest`
Expected: FAIL (class `App\Models\PlatformAdminInvitation` not found)

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('platform_admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique(); // SHA-256 hash of the plaintext token, never the token itself.
            $table->string('note')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admin_invitations');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlatformAdminInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'note',
        'invited_by',
        'expires_at',
        'accepted_at',
        'accepted_by',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return array{0: self, 1: string} [$invitation, $plainTextToken]
     */
    public static function generateFor(int $invitedBy, ?string $note = null, int $expiresInDays = 7): array
    {
        $plainTextToken = Str::random(64);

        $invitation = static::create([
            'token' => hash('sha256', $plainTextToken),
            'note' => $note,
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        return [$invitation, $plainTextToken];
    }

    public static function findValidByPlainTextToken(string $plainTextToken): ?self
    {
        return static::where('token', hash('sha256', $plainTextToken))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function status(): string
    {
        return match (true) {
            $this->isAccepted() => 'accepted',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\PlatformAdminInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlatformAdminInvitationFactory extends Factory
{
    protected $model = PlatformAdminInvitation::class;

    public function definition(): array
    {
        return [
            'token' => hash('sha256', Str::random(64)),
            'note' => null,
            'invited_by' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Run the migration and the tests**

Run: `php artisan migrate && php artisan test --filter=PlatformAdminInvitationTest`
Expected: PASS (6 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_02_200000_create_platform_admin_invitations_table.php app/Models/PlatformAdminInvitation.php database/factories/PlatformAdminInvitationFactory.php tests/Unit/Models/PlatformAdminInvitationTest.php
git commit -m "feat: add PlatformAdminInvitation model for platform admin onboarding"
```

---

### Task 5: Move Hospitals/Roles/Permissions under `platform/*` and switch to `/platform` routes

**Files:**
- Move: `resources/views/livewire/hospitals/index.blade.php` → `resources/views/livewire/platform/hospitals/index.blade.php`
- Move: `resources/views/livewire/hospitals/create.blade.php` → `resources/views/livewire/platform/hospitals/create.blade.php`
- Move: `resources/views/livewire/hospitals/edit.blade.php` → `resources/views/livewire/platform/hospitals/edit.blade.php`
- Move: `resources/views/livewire/access/roles.blade.php` → `resources/views/livewire/platform/roles/index.blade.php`
- Move: `resources/views/livewire/access/permissions.blade.php` → `resources/views/livewire/platform/permissions/index.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/livewire/users/create.blade.php:119`
- Modify: `resources/views/livewire/users/edit.blade.php:154`
- Modify (route-name-only changes, no other content): `tests/Feature/Livewire/Hospitals/IndexTest.php`, `tests/Feature/Livewire/Hospitals/CreateTest.php`, `tests/Feature/Livewire/Hospitals/InvitationTest.php`, `tests/Feature/Livewire/Hospitals/RoleVisibilityTest.php`, `tests/Feature/Livewire/Hospitals/StaffTest.php`, `tests/Feature/Livewire/Hospitals/BillingTest.php`

**Interfaces:**
- Produces: named routes `platform.hospitals.index`, `platform.hospitals.create`, `platform.hospitals.edit`, `platform.roles.index`, `platform.permissions.index`, all under URL prefix `/platform` and middleware `['auth', 'platform-admin']`.

- [ ] **Step 1: Move the Volt view files**

```bash
mkdir -p resources/views/livewire/platform/hospitals resources/views/livewire/platform/roles resources/views/livewire/platform/permissions
git mv resources/views/livewire/hospitals/index.blade.php resources/views/livewire/platform/hospitals/index.blade.php
git mv resources/views/livewire/hospitals/create.blade.php resources/views/livewire/platform/hospitals/create.blade.php
git mv resources/views/livewire/hospitals/edit.blade.php resources/views/livewire/platform/hospitals/edit.blade.php
git mv resources/views/livewire/access/roles.blade.php resources/views/livewire/platform/roles/index.blade.php
git mv resources/views/livewire/access/permissions.blade.php resources/views/livewire/platform/permissions/index.blade.php
rmdir resources/views/livewire/hospitals resources/views/livewire/access 2>/dev/null || true
```

- [ ] **Step 2: Update the moved files' internal `route()` calls**

In `resources/views/livewire/platform/hospitals/index.blade.php`, change `route('hospitals.create')` → `route('platform.hospitals.create')` and `route('hospitals.edit', $hospital->id)` → `route('platform.hospitals.edit', $hospital->id)`.

In `resources/views/livewire/platform/hospitals/create.blade.php`, change `route('hospitals.index')` → `route('platform.hospitals.index')`.

In `resources/views/livewire/platform/hospitals/edit.blade.php`, change `route('hospitals.index')` → `route('platform.hospitals.index')`.

- [ ] **Step 3: Update `routes/web.php`**

Replace:

```php
Route::middleware(['auth', 'platform-admin'])->group(function () {
    Volt::route('hospitals', 'hospitals.index')->name('hospitals.index');
    Volt::route('hospitals/create', 'hospitals.create')->name('hospitals.create');
    Volt::route('hospitals/{hospital}/edit', 'hospitals.edit')->name('hospitals.edit');

    Volt::route('roles', 'access.roles')->name('roles.index');
    Volt::route('permissions', 'access.permissions')->name('permissions.index');
});
```

with:

```php
Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform-admin'])->group(function () {
    Volt::route('hospitals', 'platform.hospitals.index')->name('hospitals.index');
    Volt::route('hospitals/create', 'platform.hospitals.create')->name('hospitals.create');
    Volt::route('hospitals/{hospital}/edit', 'platform.hospitals.edit')->name('hospitals.edit');

    Volt::route('roles', 'platform.roles.index')->name('roles.index');
    Volt::route('permissions', 'platform.permissions.index')->name('permissions.index');
});
```

- [ ] **Step 4: Update the two files outside `platform/*` that link into it**

In `resources/views/livewire/users/create.blade.php:119`, change:

```php
href="{{ Auth::user()->is_platform_admin ? route('hospitals.edit', $hospital_id) : route('users.index') }}"
```

to:

```php
href="{{ Auth::user()->is_platform_admin ? route('platform.hospitals.edit', $hospital_id) : route('users.index') }}"
```

In `resources/views/livewire/users/edit.blade.php:154`, change:

```php
href="{{ Auth::user()->is_platform_admin ? ($hospital_id ? route('hospitals.edit', $hospital_id) : route('hospitals.index')) : route('users.index') }}"
```

to:

```php
href="{{ Auth::user()->is_platform_admin ? ($hospital_id ? route('platform.hospitals.edit', $hospital_id) : route('platform.hospitals.index')) : route('users.index') }}"
```

- [ ] **Step 5: Update the affected tests' route/component names**

```bash
sed -i '' \
  -e "s/Volt::test('hospitals\.index'/Volt::test('platform.hospitals.index'/g" \
  -e "s/route('hospitals\.index'/route('platform.hospitals.index'/g" \
  tests/Feature/Livewire/Hospitals/IndexTest.php

sed -i '' \
  -e "s/Volt::test('hospitals\.create'/Volt::test('platform.hospitals.create'/g" \
  -e "s/route('hospitals\.create'/route('platform.hospitals.create'/g" \
  tests/Feature/Livewire/Hospitals/BillingTest.php tests/Feature/Livewire/Hospitals/CreateTest.php

sed -i '' \
  -e "s/Volt::test('hospitals\.edit'/Volt::test('platform.hospitals.edit'/g" \
  -e "s/route('hospitals\.edit'/route('platform.hospitals.edit'/g" \
  tests/Feature/Livewire/Hospitals/InvitationTest.php tests/Feature/Livewire/Hospitals/RoleVisibilityTest.php tests/Feature/Livewire/Hospitals/StaffTest.php tests/Feature/Livewire/Hospitals/BillingTest.php
```

Note: `tests/Feature/Livewire/Hospitals/InvitationTest.php` and `RoleVisibilityTest.php` also use `Volt::test('hospital-invitations.accept', ...)` and `Volt::test('users.create'/'users.edit', ...)` — those are unrelated components and must NOT be touched by this rename (the `sed` patterns above are scoped tightly enough not to match them, but double-check the diff before committing).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 7: Manually verify in the browser**

Log in as `thealejandro` (platform admin) on `https://xacare.test`, visit `/platform/hospitals`, `/platform/hospitals/create`, and edit a hospital via `/platform/hospitals/{id}/edit` — confirm each page loads without error and its internal links (Back, New Hospital, edit pencil) point at the new URLs. This step exists because moving Volt component directories can silently break a route that a text-only grep wouldn't catch.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor: move hospitals/roles/permissions under platform/* routes"
```

---

### Task 6: New platform layout, sidebar, and login redirect

**Files:**
- Create: `resources/views/components/layouts/platform.blade.php`
- Create: `resources/views/components/layouts/platform/sidebar.blade.php`
- Modify: `resources/views/components/layouts/app/sidebar.blade.php` (remove the platform-admin branch, lines currently ~99-114)
- Delete: `resources/views/components/layouts/app/header.blade.php` (dead code — not referenced by any layout call; verified via repo-wide grep before this plan was written)
- Modify: `resources/views/livewire/dashboard.blade.php` (redirect platform admins to `platform.dashboard`)
- Create: `resources/views/livewire/platform/dashboard.blade.php` (placeholder shell — full metrics come in Task 7)
- Modify: `routes/web.php` (register `platform.dashboard` route)
- Test: `tests/Feature/Routes/PlatformRoutesAccessTest.php`

**Interfaces:**
- Consumes: `PlatformAdminInvitation` is not used here.
- Produces: route `platform.dashboard` (URL `/platform`), layout `components.layouts.platform`. Task 7 replaces the placeholder body of `platform/dashboard.blade.php` with real metrics but keeps its `layout()` call and route untouched.

- [ ] **Step 1: Write the failing access-control test**

```php
<?php

use App\Models\Hospital;
use App\Models\User;

test('a platform admin can reach the platform dashboard', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertOk();
});

test('a hospital admin is forbidden from every platform route', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false, 'role' => 'admin']);

    $this->actingAs($admin);

    $this->get(route('platform.dashboard'))->assertForbidden();
    $this->get(route('platform.hospitals.index'))->assertForbidden();
    $this->get(route('platform.roles.index'))->assertForbidden();
    $this->get(route('platform.permissions.index'))->assertForbidden();
});

test('a guest is redirected to login for every platform route', function () {
    $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=PlatformRoutesAccessTest`
Expected: FAIL (`platform.dashboard` route not defined)

- [ ] **Step 3: Register the dashboard route**

In `routes/web.php`, inside the `Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform-admin'])->group(...)` block added in Task 5, add as the first line:

```php
Volt::route('/', 'platform.dashboard')->name('dashboard');
```

- [ ] **Step 4: Create the placeholder dashboard component**

```php
<?php

use Livewire\Volt\Component;

new class extends Component {
    public function layout(): mixed
    {
        return view('components.layouts.platform', ['title' => __('Dashboard')]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
</div>
```

- [ ] **Step 5: Create the platform layout**

```php
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-slate-50 dark:bg-zinc-950">
    <x-layouts.platform.sidebar :title="$title ?? null">
        {{ $slot }}
    </x-layouts.platform.sidebar>
</body>

</html>
```

- [ ] **Step 6: Create the platform sidebar**

Modeled directly on `resources/views/components/layouts/app/sidebar.blade.php`, but with navigation exclusive to the platform admin (no hospital-operational items at all):

```php
<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-indigo-100 dark:border-zinc-800 dark:bg-zinc-700">
    <flux:sidebar.toggle
        class="lg:hidden ml-2 bg-indigo-400 dark:bg-indigo-400 border border-indigo-600 dark:border-indigo-600 rounded-lg"
        icon="x-mark" inset="left" />

    <flux:sidebar.brand href="{{ route('platform.dashboard') }}" name="{{ config('app.name') }}"
        class="flex items-center rtl:space-x-reverse" wire:navigate>
        <x-slot name="logo" class="bg-accent text-accent-foreground border-indigo-600 dark:border-indigo-600">
            <x-app-logo-icon class="size-4 fill-none" />
        </x-slot>
    </flux:sidebar.brand>

    @php($me = auth()->user())

    <flux:navlist variant="outline">
        <flux:navlist.group :heading="__('Plataforma')" class="grid">
            <flux:navlist.item icon="chart-bar" :href="route('platform.dashboard')"
                :current="request()->routeIs('platform.dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </flux:navlist.item>
            <flux:navlist.item icon="building-office-2" :href="route('platform.hospitals.index')"
                :current="request()->routeIs('platform.hospitals.*')" wire:navigate>
                {{ __('Hospitales') }}
            </flux:navlist.item>
            <flux:navlist.item icon="clock" :href="route('platform.activity.index')"
                :current="request()->routeIs('platform.activity.*')" wire:navigate>
                {{ __('Actividad') }}
            </flux:navlist.item>
            <flux:navlist.item icon="users" :href="route('platform.admins.index')"
                :current="request()->routeIs('platform.admins.*')" wire:navigate>
                {{ __('Administradores') }}
            </flux:navlist.item>
        </flux:navlist.group>

        <flux:navlist.group :heading="__('Control de acceso')" class="grid">
            <flux:navlist.item icon="user" :href="route('platform.roles.index')"
                :current="request()->routeIs('platform.roles.index')" wire:navigate>
                {{ __('Roles') }}
            </flux:navlist.item>
            <flux:navlist.item icon="key" :href="route('platform.permissions.index')"
                :current="request()->routeIs('platform.permissions.index')" wire:navigate>
                {{ __('Permisos') }}
            </flux:navlist.item>
        </flux:navlist.group>
    </flux:navlist>

    <flux:spacer />

    <flux:sidebar.nav>
        <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')"
            wire:navigate>
            {{ __('Settings') }}
        </flux:sidebar.item>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                data-test="logout-button">
                {{ __('Log Out') }}
            </flux:sidebar.item>
        </form>
    </flux:sidebar.nav>

    <flux:dropdown class="hidden lg:block" position="bottom" align="start">
        <flux:profile :name="$me?->name" :initials="$me?->initials()"
            icon:trailing="chevrons-up-down" data-test="sidebar-menu-button" circle color="auto" />

        <flux:menu class="w-[220px]">
            <flux:menu.radio.group>
                <div class="p-0 text-sm font-normal">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                            <span
                                class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                {{ $me?->initials() }}
                            </span>
                        </span>

                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <span class="truncate font-semibold">{{ $me?->name }}</span>
                            <span class="truncate text-xs">{{ $me?->email }}</span>
                        </div>
                    </div>
                </div>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <flux:menu.radio.group>
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                </flux:menu.item>
            </flux:menu.radio.group>

            <flux:menu.separator />

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                    data-test="logout-button">
                    {{ __('Log Out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
    </flux:sidebar>

    {{ $slot }}

    @fluxScripts
```

Note: this file intentionally omits the `<!DOCTYPE html>`/`<body>` wrapper — that lives in `components/layouts/platform.blade.php` (Step 5), matching how `components/layouts/app/sidebar.blade.php` is the full document and `components/layouts/app.blade.php` just calls it directly with `<x-layouts.app.sidebar>`. Follow that exact same nesting: `components/layouts/platform.blade.php` should call `<x-layouts.platform.sidebar>` the same way `components/layouts/app.blade.php` calls `<x-layouts.app.sidebar>` — adjust Step 5 to match that pattern exactly rather than introducing a second nested `<body>`.

- [ ] **Step 7: Remove the platform-admin branch from the hospital sidebar**

In `resources/views/components/layouts/app/sidebar.blade.php`, delete the entire block:

```php
        <flux:navlist variant="outline">
            @if($me?->is_platform_admin)
                <flux:navlist.item icon="building-office-2" :href="route('hospitals.index')"
                    :current="request()->routeIs('hospitals.*')" wire:navigate>
                    {{ __('Hospitals') }}
                </flux:navlist.item>
                <flux:navlist.item icon="user" :href="route('roles.index')" :current="request()->routeIs('roles.index')"
                    wire:navigate>
                    {{ __('Roles') }}
                </flux:navlist.item>
                <flux:navlist.item icon="user" :href="route('permissions.index')"
                    :current="request()->routeIs('permissions.index')" wire:navigate>
                    {{ __('Permissions') }}
                </flux:navlist.item>
            @endif
        </flux:navlist>

```

(the whole `<flux:navlist>...</flux:navlist>` block, since it now has no other content). The hospital sidebar's `<flux:spacer />` that preceded it stays.

- [ ] **Step 8: Delete the dead header layout**

```bash
git rm resources/views/components/layouts/app/header.blade.php
```

- [ ] **Step 9: Redirect platform admins away from the generic dashboard**

`config/fortify.php` sends every user to `/dashboard` after login (`'home' => '/dashboard'`), and that page has no useful content for a platform admin. Add a redirect at the very top of `resources/views/livewire/dashboard.blade.php`'s Volt class, before the existing `with()` method:

```php
    public function mount(): void
    {
        if (Auth::user()?->is_platform_admin) {
            $this->redirect(route('platform.dashboard'), navigate: true);
        }
    }
```

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: all tests pass, including the new `PlatformRoutesAccessTest`.

- [ ] **Step 11: Manually verify in the browser**

Log in as `thealejandro` — confirm you land on `/platform` (not the old empty `/dashboard`), the sidebar shows only Dashboard/Hospitales/Actividad/Administradores/Roles/Permisos (no Ingresos/Payouts/Precios), and every link resolves. Then log in as `hospital` (regular hospital admin) — confirm their sidebar is unchanged and shows no platform-admin items, and that `/platform` returns a 403.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "feat: add dedicated platform layout, sidebar, and login redirect for platform admins"
```

---

### Task 7: Dashboard metrics

**Files:**
- Modify: `resources/views/livewire/platform/dashboard.blade.php`
- Test: `tests/Feature/Livewire/Platform/DashboardTest.php`

**Interfaces:**
- Consumes: `Hospital` (`plan`, `subscription_status`, `trial_ends_at` — all already on the model per `app/Models/Hospital.php`), `App\Enums\SubscriptionStatus` cases, `User::where('is_platform_admin', false)`, `App\Models\Activity` (Spatie Activitylog, already tenant-scoped-but-passthrough for platform admins per `TenantScope`).
- Produces: nothing consumed by later tasks (this is a leaf).

- [ ] **Step 1: Write the failing dashboard test**

```php
<?php

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('dashboard shows hospital counts by subscription status and plan', function () {
    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Trialing]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Canceled]);

    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    Volt::test('platform.dashboard')
        ->assertSet('hospitalStats.total', 4)
        ->assertSet('hospitalStats.active', 2)
        ->assertSet('hospitalStats.trialing', 1)
        ->assertSet('hospitalStats.past_due_or_canceled', 1)
        ->assertSet('hospitalStats.by_plan.basic', 2)
        ->assertSet('hospitalStats.by_plan.pro', 2);
})->skip('assertSet against public properties — see Step 3 for the actual property names used')
;

test('dashboard lists hospitals whose trial ends within 7 days', function () {
    $soon = Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(3),
    ]);
    Hospital::factory()->create([
        'subscription_status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(20),
    ]);

    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertSee($soon->name);
});

test('dashboard shows total platform users excluding platform admins themselves', function () {
    $hospital = Hospital::factory()->create();
    User::factory()->count(3)->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)
        ->get(route('platform.dashboard'))
        ->assertViewHas('totalPlatformUsers', 3);
})->skip('assertViewHas does not apply to Volt single-file components — see Step 3, verified instead via Volt::test');

test('dashboard shows recent activity entries', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $causer = User::factory()->create(['hospital_id' => $hospital->id]);

    activity()->causedBy($causer)->performedOn($hospital)->log('updated');

    Volt::test('platform.dashboard')
        ->assertSee('updated');
});
```

The two `->skip(...)` calls above exist to document intent while writing this task; delete both skipped tests in Step 4 below and replace them with the corrected assertions once the real component property names are known (Step 3 defines them as `hospitalStats`, `trialsEndingSoon`, `totalPlatformUsers`, `recentActivity` — use `Volt::test('platform.dashboard')->assertSet(...)` for all of them, not `assertViewHas`, since Volt components expose public properties directly).

- [ ] **Step 2: Run the tests to verify they fail (ignoring the two intentionally-skipped ones)**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL (component has no `hospitalStats`/`trialsEndingSoon`/`totalPlatformUsers`/`recentActivity` properties yet; 2 skipped)

- [ ] **Step 3: Implement the dashboard component**

```php
<?php

use App\Enums\SubscriptionStatus;
use App\Models\Activity;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public array $hospitalStats = [];
    public $trialsEndingSoon;
    public int $totalPlatformUsers = 0;
    public $recentActivity;

    public function mount(): void
    {
        $this->hospitalStats = [
            'total' => Hospital::count(),
            'active' => Hospital::where('subscription_status', SubscriptionStatus::Active)->count(),
            'trialing' => Hospital::where('subscription_status', SubscriptionStatus::Trialing)->count(),
            'past_due_or_canceled' => Hospital::whereIn('subscription_status', [
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Canceled,
            ])->count(),
            'by_plan' => Hospital::select('plan', DB::raw('count(*) as total'))
                ->groupBy('plan')
                ->pluck('total', 'plan'),
        ];

        $this->trialsEndingSoon = Hospital::where('subscription_status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<=', now()->addDays(7))
            ->orderBy('trial_ends_at')
            ->get();

        $this->totalPlatformUsers = User::where('is_platform_admin', false)->count();

        $this->recentActivity = Activity::with(['causer', 'subject'])->latest()->limit(10)->get();
    }

    public function layout(): mixed
    {
        return view('components.layouts.platform', ['title' => __('Dashboard')]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Hospitales totales') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $hospitalStats['total'] }}</dd>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Activos') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ $hospitalStats['active'] }}</dd>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('En trial') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ $hospitalStats['trialing'] }}</dd>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Vencidos/cancelados') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-red-600 dark:text-red-400">{{ $hospitalStats['past_due_or_canceled'] }}</dd>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Por plan') }}</flux:heading>
            <div class="space-y-2">
                @forelse($hospitalStats['by_plan'] as $plan => $count)
                    <div class="flex items-center justify-between text-sm">
                        <span class="capitalize text-zinc-700 dark:text-zinc-300">{{ $plan }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 italic">{{ __('No hay hospitales todavía.') }}</p>
                @endforelse
            </div>
            <flux:separator class="my-4" />
            <div class="flex items-center justify-between text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">{{ __('Total de usuarios en la plataforma') }}</span>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalPlatformUsers }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Trials por expirar (7 días)') }}</flux:heading>
            <div class="space-y-2">
                @forelse($trialsEndingSoon as $hospital)
                    <a href="{{ route('platform.hospitals.edit', $hospital->id) }}" wire:navigate
                        class="flex items-center justify-between text-sm hover:underline">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $hospital->name }}</span>
                        <span class="text-zinc-500">{{ $hospital->trial_ends_at->format('Y-m-d') }}</span>
                    </a>
                @empty
                    <p class="text-sm text-zinc-500 italic">{{ __('Ningún trial vence pronto.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Actividad reciente') }}</flux:heading>
            <flux:link :href="route('platform.activity.index')" wire:navigate class="text-sm">{{ __('Ver todo') }}</flux:link>
        </div>
        <div class="space-y-3">
            @forelse($recentActivity as $entry)
                <div class="text-sm text-zinc-700 dark:text-zinc-300">
                    <span class="font-medium">{{ $entry->causer?->name ?? __('Sistema') }}</span>
                    {{ $entry->description }}
                    @if($entry->subject)
                        <span class="text-zinc-500">({{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }})</span>
                    @endif
                    <span class="text-zinc-400">— {{ $entry->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-sm text-zinc-500 italic">{{ __('Sin actividad todavía.') }}</p>
            @endforelse
        </div>
    </div>
</div>
```

- [ ] **Step 4: Delete the two placeholder skipped tests and replace with real assertions**

Replace the first skipped test with:

```php
test('dashboard shows hospital counts by subscription status and plan', function () {
    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'basic', 'subscription_status' => SubscriptionStatus::Trialing]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Active]);
    Hospital::factory()->create(['plan' => 'pro', 'subscription_status' => SubscriptionStatus::Canceled]);

    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $component = Volt::actingAs($admin)->test('platform.dashboard');

    expect($component->get('hospitalStats'))->toMatchArray([
        'total' => 4,
        'active' => 2,
        'trialing' => 1,
        'past_due_or_canceled' => 1,
    ]);
    expect($component->get('hospitalStats')['by_plan']->toArray())->toBe(['basic' => 2, 'pro' => 2]);
});
```

Replace the second skipped test with:

```php
test('dashboard shows total platform users excluding platform admins themselves', function () {
    $hospital = Hospital::factory()->create();
    User::factory()->count(3)->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false]);
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $component = Volt::actingAs($admin)->test('platform.dashboard');

    expect($component->get('totalPlatformUsers'))->toBe(3);
});
```

- [ ] **Step 5: Run the full dashboard test file**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 7: Manually verify in the browser**

Visit `/platform` as `thealejandro` — confirm the four stat cards, the plan breakdown, the trials list, and the activity feed all render with real numbers from the seeded data.

- [ ] **Step 8: Commit**

```bash
git add resources/views/livewire/platform/dashboard.blade.php tests/Feature/Livewire/Platform/DashboardTest.php
git commit -m "feat: add real metrics to the platform admin dashboard"
```

---

### Task 8: Activity index page

**Files:**
- Create: `resources/views/livewire/platform/activity/index.blade.php`
- Modify: `routes/web.php` (add `platform.activity.index` route)
- Test: `tests/Feature/Livewire/Platform/ActivityIndexTest.php`

**Interfaces:**
- Consumes: `App\Models\Activity` (same as Task 7).
- Produces: route `platform.activity.index` (URL `/platform/activity`), already linked from the dashboard's "Ver todo" and the sidebar (Task 6).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Hospital;
use App\Models\User;

test('activity index lists paginated activity log entries', function () {
    $hospital = Hospital::factory()->create();
    $causer = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    activity()->causedBy($causer)->performedOn($hospital)->log('creó el hospital');

    $this->actingAs($admin)
        ->get(route('platform.activity.index'))
        ->assertOk()
        ->assertSee('creó el hospital');
});

test('a hospital admin cannot see the platform activity page', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_platform_admin' => false, 'role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('platform.activity.index'))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=ActivityIndexTest`
Expected: FAIL (`platform.activity.index` route not defined)

- [ ] **Step 3: Register the route**

In `routes/web.php`, inside the `platform.` group, add:

```php
Volt::route('activity', 'platform.activity.index')->name('activity.index');
```

- [ ] **Step 4: Implement the component**

```php
<?php

use App\Models\Activity;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public function with(): array
    {
        return [
            'activity' => Activity::with(['causer', 'subject'])->latest()->paginate(25),
        ];
    }

    public function layout(): mixed
    {
        return view('components.layouts.platform', ['title' => __('Actividad')]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Actividad reciente') }}</flux:heading>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 space-y-4">
        @forelse($activity as $entry)
            <div class="text-sm text-zinc-700 dark:text-zinc-300 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-0 last:pb-0">
                <span class="font-medium">{{ $entry->causer?->name ?? __('Sistema') }}</span>
                {{ $entry->description }}
                @if($entry->subject)
                    <span class="text-zinc-500">({{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }})</span>
                @endif
                <span class="text-zinc-400">— {{ $entry->created_at->format('Y-m-d H:i') }}</span>
            </div>
        @empty
            <p class="text-sm text-zinc-500 italic">{{ __('Sin actividad todavía.') }}</p>
        @endforelse
    </div>

    {{ $activity->links() }}
</div>
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=ActivityIndexTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add resources/views/livewire/platform/activity/index.blade.php routes/web.php tests/Feature/Livewire/Platform/ActivityIndexTest.php
git commit -m "feat: add platform activity index page"
```

---

### Task 9: Invite/list/revoke platform admins + public acceptance page

**Files:**
- Create: `resources/views/livewire/platform/admins/index.blade.php`
- Create: `resources/views/livewire/platform/admin-invitations/accept.blade.php`
- Modify: `routes/web.php` (add `platform.admins.index` inside the group, and the public `platform.admin-invitations.accept` route outside any auth middleware)
- Test: `tests/Feature/Livewire/Platform/AdminInvitationTest.php`

**Interfaces:**
- Consumes: `PlatformAdminInvitation::generateFor()`, `::findValidByPlainTextToken()`, `->status()` (Task 4).
- Produces: nothing consumed by later tasks (this is the last feature task).

- [ ] **Step 1: Write the failing tests**

```php
<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

test('a platform admin can generate an invitation for a new platform admin', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    Volt::actingAs($admin)->test('platform.admins.index')
        ->set('invitation_note', 'Soporte L2')
        ->call('generateInvitation')
        ->assertHasNoErrors()
        ->assertSet('generated_link', fn ($link) => filled($link));

    $invitation = PlatformAdminInvitation::first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->note)->toBe('Soporte L2')
        ->and($invitation->invited_by)->toBe($admin->id);
});

test('a platform admin can revoke a pending invitation', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $invitation = PlatformAdminInvitation::factory()->create(['invited_by' => $admin->id]);

    Volt::actingAs($admin)->test('platform.admins.index')
        ->call('revokeInvitation', $invitation->id)
        ->assertHasNoErrors();

    expect(PlatformAdminInvitation::find($invitation->id))->toBeNull();
});

test('a non platform admin cannot reach the admins page', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => false, 'role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('platform.admins.index'))
        ->assertForbidden();
});

test('accepting a valid platform admin invitation creates a platform admin and logs them in', function () {
    [$invitation, $plainToken] = PlatformAdminInvitation::generateFor(1, 'Test invite');

    Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', true)
        ->set('name', 'New Platform Admin')
        ->set('username', 'newplatformadmin')
        ->set('email', 'newplatformadmin@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::where('email', 'newplatformadmin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hospital_id)->toBeNull()
        ->and($user->is_platform_admin)->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->accepted_by)->toBe($user->id);

    expect(Auth::id())->toBe($user->id);
});

test('an expired platform admin invitation token is rejected with the generic message', function () {
    $invitation = PlatformAdminInvitation::factory()->expired()->create();
    $plainToken = 'expired-platform-token';
    $invitation->update(['token' => hash('sha256', $plainToken)]);

    Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});

test('a platform admin invitation token that never existed is rejected with the same generic message', function () {
    Volt::test('platform.admin-invitations.accept', ['token' => 'never-existed'])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=AdminInvitationTest`
Expected: FAIL (`platform.admins.index` / `platform.admin-invitations.accept` routes and components don't exist yet)

- [ ] **Step 3: Register the routes**

In `routes/web.php`, inside the `platform.` group, add:

```php
Volt::route('admins', 'platform.admins.index')->name('admins.index');
```

Outside any auth middleware (alongside the existing public `invitaciones/{token}` route near the top of the file), add:

```php
Volt::route('platform-invitaciones/{token}', 'platform.admin-invitations.accept')
    ->name('platform.admin-invitations.accept');
```

- [ ] **Step 4: Implement `platform.admins.index`**

```php
<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed};

state([
    'invitation_note' => '',
    'generated_link' => null,
]);

$admins = computed(fn () => User::where('is_platform_admin', true)->orderBy('name')->get());

$invitations = computed(fn () => PlatformAdminInvitation::whereNull('accepted_at')
    ->where('expires_at', '>', now())
    ->orderByDesc('created_at')
    ->get());

$generateInvitation = function () {
    [$invitation, $plainTextToken] = PlatformAdminInvitation::generateFor(
        invitedBy: Auth::id(),
        note: $this->invitation_note ?: null,
    );

    $this->generated_link = route('platform.admin-invitations.accept', $plainTextToken);
    $this->invitation_note = '';
};

$revokeInvitation = function (int $invitationId) {
    PlatformAdminInvitation::where('id', $invitationId)->delete();
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <flux:heading size="xl">{{ __('Administradores de plataforma') }}</flux:heading>

    @if($generated_link)
        <flux:callout variant="success" icon="link" heading="{{ __('Invitación generada') }}">
            <p class="text-sm mb-2">{{ __('Copia este enlace ahora — no se volverá a mostrar.') }}</p>
            <div class="flex items-center gap-2">
                <flux:input readonly value="{{ $generated_link }}" wire:key="generated-link-input" />
                <flux:button size="sm" x-on:click="navigator.clipboard.writeText('{{ $generated_link }}')">
                    {{ __('Copiar') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <flux:heading size="lg">{{ __('Invitar administrador') }}</flux:heading>
        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
            <div class="flex-1 w-full">
                <flux:input wire:model="invitation_note" label="{{ __('Nota (opcional)') }}"
                    placeholder="{{ __('ej. Soporte L2') }}" />
            </div>
            <flux:button variant="primary" wire:click="generateInvitation" class="w-full sm:w-auto">
                {{ __('Generar invitación') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Invitaciones pendientes') }}</flux:heading>
        </div>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->invitations as $invitation)
                    <tr>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $invitation->note ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $invitation->expires_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="danger" wire:click="revokeInvitation({{ $invitation->id }})"
                                wire:confirm="{{ __('¿Revocar esta invitación?') }}">
                                {{ __('Revocar') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('Sin invitaciones pendientes.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Administradores actuales') }}</flux:heading>
        </div>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($this->admins as $admin)
                    <tr>
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $admin->name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $admin->email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

- [ ] **Step 5: Implement `platform.admin-invitations.accept`**

Direct copy of `resources/views/livewire/hospital-invitations/accept.blade.php`'s structure, adapted for `PlatformAdminInvitation` and platform-admin account creation instead of hospital admin:

```php
<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, rules};

// Mirrors hospital-invitations.accept: single purpose, isolated flow, and the
// three failure modes (never existed / expired / already used) must stay
// indistinguishable to the caller.

state([
    'token' => '',
    'valid' => false,
    'name' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
    'accepted' => false,
]);

mount(function (string $token) {
    $this->token = $token;
    $this->valid = PlatformAdminInvitation::findValidByPlainTextToken($token) !== null;
});

rules(fn () => [
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
    'password' => ['required', 'string', 'min:6', 'confirmed'],
]);

$accept = function () {
    $invitation = PlatformAdminInvitation::findValidByPlainTextToken($this->token);

    if (! $invitation) {
        $this->valid = false;

        return;
    }

    $data = $this->validate();

    $user = User::create([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'hospital_id' => null,
        'is_platform_admin' => true,
        'role' => 'admin',
    ]);

    $invitation->forceFill([
        'accepted_at' => now(),
        'accepted_by' => $user->id,
    ])->save();

    Auth::login($user);

    $this->accepted = true;

    $this->redirect(route('platform.dashboard'), navigate: true);
};

?>

<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if(! $valid)
            <x-auth-header :title="__('Invalid invitation')"
                description="Este enlace de invitación no es válido o ya expiró." />

            <flux:callout variant="danger" icon="exclamation-triangle"
                heading="Este enlace de invitación no es válido o ya expiró." />

            <div class="text-center text-sm">
                <flux:link :href="route('login')" wire:navigate>{{ __('Go to login') }}</flux:link>
            </div>
        @else
            <x-auth-header :title="__('Create your account')"
                :description="__('You were invited to become an Administrador de plataforma.')" />

            <form wire:submit="accept" class="flex flex-col gap-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus
                    autocomplete="name" :placeholder="__('Full Name')" />

                <flux:input wire:model="username" :label="__('Username')" type="text" required
                    autocomplete="username" :placeholder="__('Username')" />

                <flux:input wire:model="email" :label="__('Email')" type="email" required
                    autocomplete="email" placeholder="email@example.com" />

                <flux:input wire:model="password" :label="__('Password')" type="password" required
                    autocomplete="new-password" :placeholder="__('Password')" viewable />

                <flux:input wire:model="password_confirmation" :label="__('Confirm Password')" type="password"
                    required autocomplete="new-password" :placeholder="__('Confirm Password')" viewable />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Create account') }}
                </flux:button>
            </form>
        @endif
    </div>
</x-layouts.auth>
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=AdminInvitationTest`
Expected: PASS (6 tests)

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: all tests pass.

- [ ] **Step 8: Manually verify in the browser**

As `thealejandro`, visit `/platform/admins`, generate an invitation, copy the link, open it in an incognito window, accept it with a new account, and confirm the new account lands on `/platform` and shows up under "Administradores actuales" on next visit.

- [ ] **Step 9: Commit**

```bash
git add resources/views/livewire/platform/admins resources/views/livewire/platform/admin-invitations routes/web.php tests/Feature/Livewire/Platform/AdminInvitationTest.php
git commit -m "feat: add platform admin invite/accept flow and admins list"
```

---

### Task 10: Independent security review

This is the project's established pattern for anything touching permissions or multi-tenancy (see PR #16, #17, #19, #21 in `infra-runbooks/xacare/ESTADO-Y-HANDOFF.md`): implement, run the full suite, then have an independent reviewer look at the whole diff before merging to `develop`.

- [ ] **Step 1: Confirm the full suite is green**

Run: `php artisan test`
Expected: all tests pass (183 original + new tests from Tasks 4, 6, 7, 8, 9).

- [ ] **Step 2: Dispatch an independent reviewer**

Review the entire diff on this branch since it diverged from `develop` (`git diff develop...HEAD`), with explicit focus on:
- Every gateo that used to check `is_super_admin`/`superadmin` still enforces the exact same rule after the rename — not just that the name changed, but that no `abort_if`/`abort_unless`/middleware check was accidentally inverted, dropped, or narrowed/widened during the sed-based bulk rename (Task 3).
- The `/platform/*` route group and every route inside it are unreachable by a non-platform-admin (403) and by a guest (redirect to login).
- The `PlatformAdminInvitation` flow cannot be used to create a platform-admin account without a valid, unexpired, unused invitation — check `findValidByPlainTextToken` and the `accept` handler in `platform.admin-invitations.accept` specifically for the same class of bug PR #21 found in `HospitalInvitation` (a public Livewire property being trusted instead of a server-validated source).
- The historical migration `2026_01_07_165455_add_qxlog_fields_to_users_table.php` was NOT edited (only the new rename migration touches the column).
- No production data loss risk: the `renameColumn` migration is reversible and non-destructive.

- [ ] **Step 3: Address any findings, then re-run the full suite**

Run: `php artisan test`
Expected: all tests pass after fixes.

- [ ] **Step 4: Final commit (if fixes were needed)**

```bash
git add -A
git commit -m "fix: address independent review findings for platform admin panel"
```

At this point the branch is ready for the user to review and decide when to merge to `develop` — never merge to `main` without asking first.
