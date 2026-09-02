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

Route::middleware(['auth', 'hospital.subscribed'])->group(function () {
    Volt::route('procedures/create', 'procedures.create')->name('procedures.create');

    Volt::route('instrumentist/payouts', 'instrumentist.payouts')->name('instrumentist.payouts');
    Volt::route('instrumentist/payouts/{batch}/voucher', 'payouts.voucher')->name('instrumentist.payouts.voucher');
});

Route::middleware(['auth', 'admin', 'hospital.subscribed'])->group(function () {
    Volt::route('patients', 'patients.index')->name('patients.index');
    Volt::route('patients/create', 'patients.create')->name('patients.create');
    Volt::route('admissions/create', 'admissions.create')->name('admissions.create');

    Volt::route('payouts/create', 'payouts.create')->name('payouts.create');
    Volt::route('payouts/{batch}/voucher', 'payouts.voucher')->name('payouts.voucher');
    Volt::route('payouts', 'payouts.index')->name('payouts.index');

    Volt::route('procedures', 'procedures.index')->name('procedures.index');
    Volt::route('procedures/{procedure}/edit', 'procedures.edit')->name('procedures.edit');

    Volt::route('pricing/settings', 'pricing.settings')->name('pricing.settings');
    Volt::route('pricing/instrumentists', 'pricing.instrumentist')->name('pricing.instrumentists');

    Volt::route('settings/organization', 'settings.organization')->name('settings.organization');

    Volt::route('seguros', 'modules.insurance')
        ->middleware('hospital.feature:insurance')
        ->name('modules.insurance');
});

Route::middleware(['auth', 'hospital.subscribed'])->group(function () {
    // Sin middleware `admin` (Spatie hasRole('admin')) a propósito: el super admin no
    // siempre tiene ese role Spatie asignado, solo el flag `is_super_admin`. Cada
    // componente Volt valida `is_super_admin || role === 'admin'` en su propio mount(),
    // igual que procedures.index. El admin de hospital gestiona el staff de su propio
    // tenant (TenantScope lo limita); el super admin gestiona el de cualquier hospital.
    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.create')->name('users.create');
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');
});

Route::middleware(['auth', 'superadmin'])->group(function () {
    Volt::route('hospitals', 'hospitals.index')->name('hospitals.index');
    Volt::route('hospitals/create', 'hospitals.create')->name('hospitals.create');
    Volt::route('hospitals/{hospital}/edit', 'hospitals.edit')->name('hospitals.edit');

    Volt::route('roles', 'access.roles')->name('roles.index');
    Volt::route('permissions', 'access.permissions')->name('permissions.index');
});
