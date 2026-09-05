<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Public, unauthenticated invitation acceptance route. Single purpose: let
// someone without an account create the first admin account for the
// hospital named on their invitation. Isolated from every other route.
Volt::route('invitaciones/{token}', 'hospital-invitations.accept')->name('hospital-invitations.accept');

// Public, unauthenticated invitation acceptance route for platform admins.
// Single purpose: let someone without an account create a new platform-admin
// account from a one-time invitation link. Isolated from every other route.
Volt::route('platform-invitaciones/{token}', 'platform.admin-invitations.accept')
    ->name('platform.admin-invitations.accept');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'hospital.subscribed'])
    ->name('dashboard');

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
    Volt::route('admissions/create', 'admissions.create')->name('admissions.create');

    Volt::route('settings/organization', 'settings.organization')->name('settings.organization');
    Volt::route('settings/roles', 'settings.roles.index')->name('settings.roles.index');

    Volt::route('seguros', 'modules.insurance')
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
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');
});

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
