# Fix de hallazgos de seguridad: aislamiento entre hospitales

Este brief consolida los hallazgos Critical + Important de un review de seguridad adversarial (findings-only,
ya completado, sin código modificado todavía). Implementar en el orden dado; cada parte es independiente
pero todas deben quedar en el mismo trabajo antes de mergear.

## Contexto del review (ya hecho, no repetir)

Se probó adversarialmente el aislamiento entre hospitales: IDOR por URL, manipulación de IDs en estado de
Livewire, precedencia de `orWhere`, uso de `withoutGlobalScopes()`. **El aislamiento entre usuarios normales
de distintos hospitales está sólido** — nada de eso funcionó. El problema real es el **super admin**.

## Parte A (CRITICAL — la más importante): bloqueo estructural de escritura del super admin

### El problema

El rediseño "super admin es de solo lectura operativa" (de una auditoría anterior de Fase 0) se implementó
como 4 líneas `abort_if((bool) Auth::user()->is_super_admin, 403, '...')` copiadas a mano en 4 archivos
(`patients/create.blade.php`, `admissions/create.blade.php`, `procedures/create.blade.php`,
`payouts/create.blade.php`). Todos los flujos de escritura agregados después (Fase 1 completa: editar/borrar
casos, tarifario, `RoleRate`, `RateModifier`, etc.) NO tienen esa protección, porque depende de que cada
implementador se acuerde de copiarla. Se probó y confirmó: un super admin puede editar/borrar casos de
cualquier hospital, sobrescribir tarifas de cualquier hospital, y modificar usuarios de cualquier hospital —
sin ninguna indicación en pantalla de qué hospital está tocando.

### El fix (estructural, no otro `abort_if` copiado)

Modificar `app/Models/Concerns/BelongsToTenant.php` para que el trait mismo bloquee la escritura (create/
update/delete) de cualquier modelo que lo use, cuando quien está autenticado es un super admin (`hospital_id
=== null`), **salvo que el modelo se declare explícitamente exento**.

```php
<?php

namespace App\Models\Concerns;

use App\Models\Hospital;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (! $model->hospital_id && Auth::check() && Auth::user()->hospital_id) {
                $model->hospital_id = Auth::user()->hospital_id;
            }
        });

        static::saving(function ($model) {
            static::abortIfSuperAdminWriteBlocked();
        });

        static::deleting(function ($model) {
            static::abortIfSuperAdminWriteBlocked();
        });
    }

    protected static function abortIfSuperAdminWriteBlocked(): void
    {
        if (static::allowsSuperAdminWrites()) {
            return;
        }

        if (Auth::check() && Auth::user()->is_super_admin) {
            abort(403, 'Super admin es de solo lectura sobre datos operativos de hospitales.');
        }
    }

    /**
     * Los modelos que el super admin sí necesita poder escribir (ej. User, para gestión de
     * administradores de hospital) deben sobreescribir esto a `true`.
     */
    public static function allowsSuperAdminWrites(): bool
    {
        return false;
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
```

Revisar exactamente qué modelos usan `BelongsToTenant` (`grep -rln "use HasFactory.*BelongsToTenant\|BelongsToTenant" app/Models/`
o similar) — al momento de escribir este brief son: `User`, `SurgicalRole`, `SurgicalAssignment`, `RoleRate`,
`PricingSetting`, `RateModifier`, `PayoutBatch`, `PayoutItem`, `SurgicalCase`, `Patient`, `Admission` (y
próximamente `HospitalInvitation`, que se está construyendo en paralelo en otra rama — no te preocupes por
esa, no existe todavía en esta rama).

**Solo `User` debe sobreescribir `allowsSuperAdminWrites()` a `true`** (el super admin legítimamente crea/
edita usuarios y administradores de cualquier hospital vía `/users`, gestión ya protegida por su propio
middleware `superadmin`). Todos los demás se quedan bloqueados por default.

```php
// app/Models/User.php, agregar dentro de la clase:
public static function allowsSuperAdminWrites(): bool
{
    return true;
}
```

### Excepción dentro de la excepción: `pricing/instrumentist.blade.php`

Con `User` exento a nivel de modelo, el `toggle()` en `resources/views/livewire/pricing/instrumentist.blade.php`
(que hace `User::findOrFail($id)->save()` para cambiar `use_pay_scheme`) va a seguir permitiendo que un super
admin lo toque — pero ESTE caso específico SÍ debe seguir bloqueado (es un ajuste operativo/financiero, no
gestión administrativa de usuarios). Revisar ese archivo y agregar un `abort_if((bool) Auth::user()->is_super_admin, 403, '...')`
explícito ahí, igual que los 4 archivos de Fase 0 ya lo tienen — este es un caso puntual y entendido, no el
patrón general que se está corrigiendo.

### Verificación obligatoria

Reproducir EXACTAMENTE los escenarios que el review ya probó y confirmar que ahora fallan con 403:
- `procedures.edit` → `save()` sobre un caso de otro hospital.
- `procedures.index` → `delete()` sobre un caso de otro hospital.
- `pricing.settings` → `saveBaseRate()` sobre un rol de otro hospital.
- `pricing.instrumentist` → `toggle()` sobre un usuario de otro hospital.

Escribir tests nuevos para estos 4 casos (ver Parte E, se puede combinar con los tests de esa parte).

**Correr toda la suite existente después de este cambio** — es el paso de mayor riesgo de romper algo, porque
el bloqueo es amplio. Si algún test legítimo hace que un super admin cree/edite datos tenant-scoped como parte
de su propio setup (no como ataque), hay que decidir caso por caso: ¿el test debería usar un usuario admin
normal en vez de super admin para ese setup? (probablemente sí, en la mayoría de los casos) — ajustar el test,
no debilitar el guard.

## Parte B (IMPORTANT): `xacare:backfill-patients` mezcla nombres de pacientes entre hospitales

`app/Console/Commands/BackfillPatients.php` (líneas ~31-51): plucks nombres de `SurgicalCase` sin filtrar por
hospital, crea `Patient`s todos bajo el hospital `--hospital` (default `hnsc`), y hace un `update()` que
matchea `patient_name` cruzando TODOS los hospitales. Si se vuelve a correr sobre la base actual (ya
multi-tenant), esto puede filtrar nombres de pacientes de un hospital a otro y crear relaciones cruzadas
inválidas.

**Fix**: reestructurar el comando para procesar hospital por hospital — el loop principal debe iterar sobre
cada `Hospital`, y dentro de cada iteración, las queries de `SurgicalCase`/`Patient`/el `update()` de matching
deben ir explícitamente filtradas por `hospital_id = $hospital->id` (no confiar en el scope automático, este
comando corre sin usuario autenticado). Revisar el archivo completo antes de tocarlo — mantener el
`{--hospital=}` option existente para poder correrlo contra uno solo si hace falta, pero que el default
(sin la opción) itere hospital por hospital, nunca cruzando datos entre ellos.

Este comando no es alcanzable desde la web (no tiene ruta), es una herramienta de mantenimiento — igual hay
que corregirlo porque es real y puede correr en cualquier momento por error.

## Parte C (IMPORTANT): reglas `exists:` sin scope de hospital

En `resources/views/livewire/admissions/create.blade.php`, `resources/views/livewire/procedures/create.blade.php`,
y `resources/views/livewire/payouts/create.blade.php`, las reglas de validación como `'patient_id' => ['exists:patients,id']`
no filtran por hospital — permiten que alguien "adivine" IDs de otros hospitales por fuerza bruta (probando
qué IDs pasan la validación) y, en el caso de `admissions.create`, efectivamente crear un `Admission` del
hospital propio apuntando a un `Patient` de OTRO hospital (confirmado con un test real en el review).

**Fix**: reemplazar cada `exists:tabla,columna` relevante por una regla `Rule::exists('tabla', 'columna')->where('hospital_id', $hospitalId)`
(usando `Illuminate\Validation\Rule`), donde `$hospitalId` es el hospital del usuario autenticado (`Auth::user()->hospital_id`).
Revisar cada uno de los 3 archivos y aplicar esto a TODAS las reglas `exists:` que referencien un modelo con
`hospital_id` (`patients`, `surgical_roles`, `users` — pero para `users`, `role_id` en asignaciones no
necesariamente exige que el usuario asignado sea del mismo hospital si hay circulantes/doctores externos;
usar criterio, pero como mínimo `patient_id`/`surgical_roles` deben quedar scopeados, y documentar en el
reporte si dejás alguna sin scope y por qué).

## Parte D (IMPORTANT, pedido explícito del dueño del proyecto): `activity_log` sin aislamiento por hospital

La tabla `activity_log` (de `spatie/laravel-activitylog`, usada para auditar cambios de honorario/tarifario)
no tiene columna `hospital_id` ni scope de tenant. Hoy no hay fuga real (solo se lee a través de una relación
ya scopeada, `$assignment->activities`), pero si en el futuro se construye cualquier vista tipo "todo el
historial de cambios" sin pasar por esa relación, filtraría datos de todos los hospitales.

**Fix**: agregar `hospital_id` a la tabla `activity_log` vía migración (`nullable` al inicio para no romper
registros existentes que no tengan forma de inferir el hospital, aunque en la práctica todos los registros
actuales sí deberían poder inferirlo — decidir con criterio: lo más simple es agregar la columna nullable,
y poblarla vía backfill en la misma migración para las filas existentes, infiriendo el `hospital_id` desde el
`subject` polimórfico: `SurgicalAssignment`/`RoleRate`/`RateModifier` todos tienen `hospital_id` propio,
usar eso). Extender el modelo `Activity` (publicar/extender `Spatie\Activitylog\Models\Activity` si el
proyecto no lo hace ya — revisar `config/activitylog.php` para ver si hay un modelo custom configurado) para
que use `BelongsToTenant` (con `allowsSuperAdminWrites()` en `false`, ya que esta tabla nunca debería
escribirse manualmente, solo vía el paquete) y así quede protegida automáticamente igual que el resto — tanto
de lectura cruzada como de escritura por super admin.

Asegurarse de que el paquete siga poblando `hospital_id` automáticamente al loguear una actividad (revisar si
hace falta un listener/observer que copie `hospital_id` del `subject` al crear el `Activity`, ya que el
paquete mismo no sabe de multi-tenancy — el trait `BelongsToTenant`'s `creating()` hook solo llena
`hospital_id` desde `Auth::user()->hospital_id`, lo cual debería funcionar bien ya que quien dispara el log es
siempre un usuario autenticado de un hospital específico, pero confirmar con un test que el `hospital_id` del
`Activity` resultante coincide con el del modelo auditado).

## Parte E (IMPORTANT): tests negativos faltantes

No existen tests que prueben explícitamente "esto debe fallar" para el límite entre hospitales en ningún área
de Fase 1. Crear `tests/Feature/Tenancy/SuperAdminReadOnlyFase1Test.php` (mirroring el `SuperAdminReadOnlyTest.php`
existente de Fase 0 — revisarlo primero para seguir su convención) cubriendo, como mínimo:

1. Super admin NO puede editar (`save()`) un `SurgicalCase`/`SurgicalAssignment` de un hospital ajeno (403).
2. Super admin NO puede borrar un `SurgicalCase` (403).
3. Super admin NO puede guardar/crear un `RoleRate` (403).
4. Super admin NO puede modificar `use_pay_scheme` de un `User` vía `pricing.instrumentist` (403).
5. Un admin normal de Hospital A NO puede crear un `Admission` apuntando a un `Patient` de Hospital B
   (validación falla, ya no pasa `exists:`).
6. `xacare:backfill-patients` corrido sobre 2 hospitales con casos/nombres distintos no mezcla pacientes entre
   ellos (crear el escenario, correr el comando, verificar que cada `Patient` quedó en su hospital correcto y
   que ningún `SurgicalCase` de un hospital quedó vinculado a un `Patient` de otro).
7. Un `Activity` logueado al editar un `SurgicalAssignment` tiene el `hospital_id` correcto, y una query de
   `Activity` scopeada (como cualquier otro modelo con `BelongsToTenant`) no devuelve actividades de otro
   hospital al estar autenticado como un usuario de un hospital distinto.

## Verificación final

Correr la suite completa. Baseline actual: 125/125 en `develop` (contar los tests que agregues, y verificar
si algún test EXISTENTE necesita ajustarse porque legítimamente usaba un super admin para setup de datos
tenant-scoped — ajustar esos tests para usar un admin normal en su lugar, documentar cada ajuste en el
reporte).

## Commits

Commits separados por parte (A, B, C, D, E) tiene sentido para que se puedan revisar por separado — tu
criterio final. Mensajes planos, convencionales. Sin ningún tipo de atribución a Claude/Anthropic — regla
dura del proyecto.
