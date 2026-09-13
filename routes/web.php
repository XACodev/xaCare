<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Público, sin autenticación. Endpoint de monitoreo externo: verifica DB y cache.
Route::get('/health', HealthController::class)->name('health');

// Páginas legales públicas, sin autenticación.
Route::view('terms', 'legal.terms')->name('legal.terms');
Route::view('privacy', 'legal.privacy')->name('legal.privacy');

// Public, unauthenticated invitation acceptance route. Single purpose: let
// someone without an account create the first admin account for the
// hospital named on their invitation. Isolated from every other route.
Volt::route('invitaciones/{token}', 'hospital-invitations.accept')
    ->middleware('throttle:10,1')
    ->name('hospital-invitations.accept');

// Public, unauthenticated invitation acceptance route for platform admins.
// Single purpose: let someone without an account create a new platform-admin
// account from a one-time invitation link. Isolated from every other route.
Volt::route('platform-invitaciones/{token}', 'platform.admin-invitations.accept')
    ->middleware('throttle:10,1')
    ->name('platform.admin-invitations.accept');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'hospital.subscribed'])
    ->name('dashboard');

Volt::route('suscripcion-suspendida', 'billing.suspended')
    ->middleware(['auth'])
    ->name('billing.suspended');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

Route::middleware(['auth', 'admin', 'hospital.subscribed'])->group(function () {
    Volt::route('patients', 'patients.index')->name('patients.index');
    Volt::route('patients/create', 'patients.create')->name('patients.create');
    Volt::route('patients/{patient}', 'patients.show')->name('patients.show');
    Volt::route('patients/{patient}/edit', 'patients.edit')->name('patients.edit');
    Volt::route('admissions', 'admissions.index')->name('admissions.index');
    Volt::route('admissions/create', 'admissions.create')->name('admissions.create');
    Volt::route('admissions/{admission}', 'admissions.show')->name('admissions.show');

    Route::get('admissions/{admission}/documents/{type}', function (\App\Models\Admission $admission, string $type) {
        abort_unless($admission->hospital_id === auth()->user()->hospital_id, 404);
        abort_unless(in_array($type, ['dpi', 'firma'], true), 404);

        $path = $type === 'dpi' ? $admission->dpi_path : $admission->firma_path;
        abort_if(blank($path), 404);

        return response(\App\Support\EncryptedFileStorage::retrieve('local', $path))
            ->header('Content-Type', 'application/octet-stream');
    })
        ->name('admissions.documents.show')
        ->middleware('hospital.feature:admissions_id_documents');

    // El QR impreso solo trae el token, nunca la URL: quien lo escanea es
    // siempre esta app, que resuelve el token aqui y redirige. Asi el QR
    // sigue funcionando aunque cambiemos rutas despues, y nadie ve a que
    // apunta con solo mirarlo.
    Route::get('qr/{token}', function (string $token) {
        $admission = \App\Models\Admission::where('qr_token', $token)->firstOrFail();

        return redirect()->route('admissions.show', ['admission' => $admission, 'token' => $admission->qr_token]);
    })->name('qr.resolve');

    Volt::route('settings/organization', 'settings.organization')->name('settings.organization');
    Volt::route('settings/roles', 'settings.roles.index')->name('settings.roles.index');
    Volt::route('settings/wards', 'settings.wards')->name('settings.wards');
    Volt::route('settings/hospital-rooms', 'settings.hospital-rooms')->name('settings.hospital-rooms');
    Volt::route('settings/patient-categories', 'settings.patient-categories')->name('settings.patient-categories');

    Volt::route('settings/admission-types', 'settings.admission-types')
        ->name('settings.admission-types')
        ->middleware('hospital.feature:admissions_custom_form');

    Volt::route('settings/admission-types/{admissionType}/edit', 'settings.admission-types-edit')
        ->name('settings.admission-types.edit')
        ->middleware('hospital.feature:admissions_custom_form');

    Volt::route('seguros', 'insurance.index')
        ->middleware('hospital.feature:insurance')
        ->name('modules.insurance');
});

Route::middleware(['auth', 'hospital.subscribed'])->group(function () {
    // Sin middleware `admin` (Spatie hasRole('admin')) a propósito: el administrador de
    // plataforma no siempre tiene ese role Spatie asignado, solo el flag `is_platform_admin`.
    // Cada componente Volt valida su propio acceso en mount(), igual que procedures.index.
    // `users.index` es solo para el admin de hospital (su propio staff, vía TenantScope).
    // El administrador de plataforma gestiona staff desde la ficha de cada hospital (hospitals.edit,
    // sección "Staff"), y crea/edita usando estas mismas dos rutas pasando `?hospital_id=`.
    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.create')->name('users.create');
    Volt::route('users/{user}', 'users.show')->name('users.show');
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');
});

Route::prefix('platform')->name('platform.')->middleware(['auth', 'platform-admin', 'platform-2fa'])->group(function () {
    Volt::route('/', 'platform.dashboard')->name('dashboard');

    Volt::route('hospitals', 'platform.hospitals.index')->name('hospitals.index');
    Volt::route('hospitals/create', 'platform.hospitals.create')->name('hospitals.create');
    Volt::route('hospitals/{hospital}/edit', 'platform.hospitals.edit')->name('hospitals.edit');

    Volt::route('billing/reports', 'platform.billing.reports')->name('billing.reports');

    Volt::route('roles', 'platform.roles.index')->name('roles.index');
    Volt::route('permissions', 'platform.permissions.index')->name('permissions.index');

    Volt::route('activity', 'platform.activity.index')->name('activity.index');
    Volt::route('admins', 'platform.admins.index')->name('admins.index');
});

// Alias sin nombre fuera del grupo `platform.*`: no forma parte de la auditoría de rutas
// nombradas de plataforma (PlatformAccessTest), solo redirige a la ruta real.
Route::redirect('platform/dashboard', '/platform');
