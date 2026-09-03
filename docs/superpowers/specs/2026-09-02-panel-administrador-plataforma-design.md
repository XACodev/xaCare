# Panel de Administrador de plataforma — Diseño

> 2026-09-02. Sigue a PR #22 (rename visual "Super Admin" → "Administrador de plataforma"). Ver `infra-runbooks/xacare/ESTADO-Y-HANDOFF.md` para el checkpoint completo del proyecto.

## Contexto

Hoy el "Administrador de plataforma" (`is_super_admin = true`) no tiene panel propio: comparte el layout y el sidebar del admin de hospital, que le muestra secciones operativas de hospital (Ingresos, Payouts, Configuración de precios) que no le aplican, seguido de `Hospitales`/`Roles`/`Permisos` al final. El dashboard (`resources/views/livewire/dashboard.blade.php`) no tiene ninguna rama para él — cae al `else` genérico, solo ve el atajo "Mi Perfil", sin ninguna métrica.

Además, el término "superadmin" sigue viviendo en el código (middleware, clases, columna de BD) pese a que el texto visible ya se renombró a "Administrador de plataforma" en PR #22. Se corrige ahora, antes de que la superficie de código crezca más y el rename se vuelva más costoso.

Este documento cubre ambos: el rename de terminología y el panel nuevo, como una sola entrega.

## Alcance

**Incluido:**
1. Rename de `superadmin`/`is_super_admin` a `platform-admin`/`is_platform_admin` en todo el código (middleware, clases, métodos de dominio, vistas, tests) y migración de columna en BD.
2. Rutas `/platform/*` con layout y sidebar propios para el Administrador de plataforma.
3. Dashboard con métricas de salud del SaaS (hospitales por estado/plan, trials por vencer, total de usuarios) + feed de actividad reciente.
4. Invitaciones para dar de alta nuevos Administradores de plataforma (mismo patrón que `HospitalInvitation`, sin `hospital_id`).
5. Página "Administradores de plataforma": lista de admins actuales + invitaciones pendientes, invitar, revocar.

**Fuera de alcance (YAGNI, no pedido):**
- Desactivar/revocar el acceso de un Administrador de plataforma ya existente.
- Cambiar quién puede aceptar una invitación de plataforma más allá del flujo de token de un solo uso.
- Cualquier tema de Stripe/Cashier (billing sigue siendo manual, sin cambios aquí).
- Subdominio propio para el panel de plataforma (se queda en el mismo dominio, bajo `/platform`).

## 1. Rename de terminología: `superadmin` → `platform-admin`

### Base de datos
- Nueva migración: `Schema::table('users', fn ($t) => $t->renameColumn('is_super_admin', 'is_platform_admin'))`.
- `renameColumn` preserva todos los datos existentes — no destructiva. En producción se aplica sola con el `migrate --force` normal del deploy (regla dura del proyecto: nunca comandos artisan custom en prod).

### Código
- Middleware: alias `superadmin` → `platform-admin` (`bootstrap/app.php`); clase `SuperAdminOnly` → `PlatformAdminOnly` (`app/Http/Middleware/`).
- `User::allowsSuperAdminWrites()` → `User::allowsPlatformAdminWrites()`; mismo rename en `HospitalInvitation` y en `BelongsToTenant::abortIfSuperAdminWriteBlocked()` → `abortIfPlatformAdminWriteBlocked()`.
- Toda referencia a `is_super_admin` en modelos, ~15 componentes Livewire (`hospitals/*`, `users/*`, `procedures/*`, `patients/*`, `admissions/create`, `pricing/instrumentist`, `payouts/create`, los layouts `app/header.blade.php` y `app/sidebar.blade.php`) pasa a `is_platform_admin`.
- Tests: renombrar archivos/clases que llevan "SuperAdmin" en el nombre (`SuperAdminReadOnlyFase1Test` → `PlatformAdminReadOnlyFase1Test`, `SuperAdminReadOnlyTest` → `PlatformAdminReadOnlyTest`, helpers `makeSuperAdmin()` → `makePlatformAdmin()`, etc.) para que quede consistente de punta a punta, no solo el código de producción.

### Verificación punto por punto (no solo buscar-y-reemplazar)
Cada gateo de seguridad que usa el flag se revisa individualmente para confirmar que el comportamiento no cambia, solo el nombre:
- `BelongsToTenant` (bloqueo de escritura cross-tenant del admin de plataforma).
- `AdminAuth` (permite acceso sin rol Spatie `admin` cuando `is_platform_admin`).
- `EnsureHospitalSubscribed`, `EnsureHospitalFeature` (bypass para admin de plataforma).
- Los `abort_if`/`abort_unless` en los ~15 componentes Livewire listados arriba.

## 2. Rutas y layout del panel de plataforma

Prefijo `/platform/*`, nombre de ruta `platform.*`, bajo el middleware `['auth', 'platform-admin']`:

```php
Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform-admin'])->group(function () {
    Volt::route('/', 'platform.dashboard')->name('dashboard');
    Volt::route('hospitals', 'platform.hospitals.index')->name('hospitals.index');
    Volt::route('hospitals/create', 'platform.hospitals.create')->name('hospitals.create');
    Volt::route('hospitals/{hospital}/edit', 'platform.hospitals.edit')->name('hospitals.edit');
    Volt::route('roles', 'platform.roles.index')->name('roles.index');
    Volt::route('permissions', 'platform.permissions.index')->name('permissions.index');
    Volt::route('activity', 'platform.activity.index')->name('activity.index');
    Volt::route('admins', 'platform.admins.index')->name('admins.index');
});

// Pública, sin auth — mismo patrón que hospital-invitations/{token}
Volt::route('platform-invitaciones/{token}', 'platform.admin-invitations.accept')
    ->name('platform.admin-invitations.accept');
```

- Componentes Volt existentes de `hospitals/*`, `access/roles`, `access/permissions` se mueven a `resources/views/livewire/platform/*` (reorganización de directorio; la lógica interna no cambia salvo el rename de la sección 1).
- Nuevo layout `resources/views/components/layouts/platform.blade.php` con su propio `components/layouts/platform/sidebar.blade.php`: mismos componentes Flux/mismo sistema visual que el layout de hospital, pero navegación exclusiva — Dashboard, Hospitales, Actividad, Administradores, Roles, Permisos. Ningún ítem operativo de hospital.
- El layout de hospital (`components/layouts/app/sidebar.blade.php`) pierde por completo el branching de `is_platform_admin` — ese sidebar vuelve a ser 100% sobre operación de hospital.
- Actualizar las ~10 referencias a `route('hospitals.*')`, `route('roles.index')`, `route('permissions.index')` a los nuevos nombres `platform.*` (`users/create.blade.php`, `users/edit.blade.php`, `header.blade.php`, tests de `Hospitals/*`, `routes/web.php`).

## 3. Dashboard — métricas y actividad

`resources/views/livewire/platform/dashboard.blade.php`:

```php
public function with(): array
{
    return [
        'hospital_stats' => [
            'total' => Hospital::count(),
            'active' => Hospital::where('subscription_status', SubscriptionStatus::Active)->count(),
            'trialing' => Hospital::where('subscription_status', SubscriptionStatus::Trialing)->count(),
            'past_due_or_canceled' => Hospital::whereIn('subscription_status', [
                SubscriptionStatus::PastDue, SubscriptionStatus::Canceled,
            ])->count(),
            'by_plan' => Hospital::select('plan', DB::raw('count(*) as total'))->groupBy('plan')->pluck('total', 'plan'),
        ],
        'trials_ending_soon' => Hospital::where('subscription_status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<=', now()->addDays(7))
            ->orderBy('trial_ends_at')
            ->get(),
        'total_platform_users' => User::where('is_platform_admin', false)->count(),
        'recent_activity' => Activity::with(['causer', 'subject'])->latest()->limit(10)->get(),
    ];
}
```

- Tarjetas de estadística arriba (mismo lenguaje visual que las tarjetas ya existentes en `dashboard.blade.php` para admin de hospital): Hospitales totales, Activos, En trial, Vencidos/cancelados, Total de usuarios en la plataforma.
- Desglose por plan (basic/pro) como lista simple con conteos.
- Lista "Trials por expirar" (próximos 7 días), cada fila enlaza a `platform.hospitals.edit` del hospital correspondiente.
- Feed "Actividad reciente": reusa `spatie/laravel-activitylog` (ya instalado, usado en Fase 1 para auditoría de honorarios), sin filtrar por hospital — el admin de plataforma ve todo. Cada entrada: quién (causer), qué acción, sobre qué modelo (subject), cuándo, en español.
- Página `platform.activity.index` separada: el mismo feed pero paginado/completo, no solo las últimas 10 del dashboard.

## 4. Invitaciones para nuevos Administradores de plataforma

Nuevo modelo `PlatformAdminInvitation` (`app/Models/PlatformAdminInvitation.php`), mismo patrón que `HospitalInvitation` pero **sin** `hospital_id` — no es tenant-scoped:

```php
protected $fillable = ['token', 'note', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by'];
protected $hidden = ['token'];

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
```

Migración `platform_admin_invitations`: `id`, `token` (string, indexado), `note` (nullable), `invited_by` (FK `users.id`), `expires_at`, `accepted_at` (nullable), `accepted_by` (FK `users.id`, nullable), timestamps.

- `platform.admins.index`: lista admins actuales (`is_platform_admin = true`) + invitaciones pendientes. Botón "Invitar administrador" (nombre no se pide aquí — solo email/nota, el nombre lo define quien acepta, igual que `HospitalInvitation`). Botón "Revocar" en invitaciones pendientes (borra el registro o marca expirado — a decidir en implementación, sin impacto de diseño).
- Ruta pública `platform-invitaciones/{token}` → `platform.admin-invitations.accept`: mismo componente que `hospital-invitations.accept` en estructura (mensaje idéntico para token inexistente/expirado/usado, revalida en submit). Al aceptar: crea `User` con `is_platform_admin = true`, `hospital_id = null`, **sin** asignar rol Spatie (el flag es la fuente de verdad, igual que hoy).

## 5. Seguridad y testing

- **Revisor independiente obligatorio** antes de mergear a `develop` (patrón ya usado en esta sesión para permisos/multi-tenancy), con foco en:
  - Ningún gateo de seguridad existente se debilitó por el rename (comparar comportamiento antes/después, no solo el nombre).
  - Las rutas `/platform/*` y el flujo de invitación de admin no abren una vía de escalación de privilegios (nadie sin invitación válida puede terminar con `is_platform_admin = true`).
  - Un admin de hospital normal recibe 403/404 en toda ruta `/platform/*`.
- **Tests nuevos:**
  - Dashboard: métricas correctas dado un set de hospitales con distintos planes/estados; solo accesible a `is_platform_admin`.
  - `PlatformAdminInvitation`: generar, aceptar (feliz), token inexistente/expirado/ya usado (mensaje idéntico en los 3 casos), revocar.
  - Acceso: admin de hospital normal bloqueado en cada ruta `/platform/*`.
- **Tests existentes**: renombrados (sección 1) conservando su cobertura exacta, sin cambios de comportamiento.
- Suite completa (183 tests hoy, más los nuevos) debe quedar en verde antes de considerar el trabajo listo para review.

## Riesgos

- Superficie de cambio grande en el rename (~150 referencias) — mitigado por revisión punto por punto de cada gateo, no solo grep-and-replace, más el revisor independiente.
- Mover archivos Volt de directorio puede romper referencias no detectadas por grep textual (ej. convenciones de nombre de componente Volt basadas en ruta de archivo) — verificar cada ruta afectada manualmente en Herd tras el rename, no solo confiar en los tests.
- Ninguna migración de este diseño es destructiva (rename de columna, tabla nueva) — no hay riesgo de pérdida de datos de producción.

## No mergear a `main`

Como con todo el trabajo de esta sesión: se implementa y mergea a `develop`. La decisión de cuándo mergear `develop` → `main` sigue siendo exclusiva del usuario.
