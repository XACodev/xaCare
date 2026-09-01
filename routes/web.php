<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
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

Route::middleware(['auth'])->group(function () {
    Volt::route('procedures/create', 'procedures.create')->name('procedures.create');

    Volt::route('instrumentist/payouts', 'instrumentist.payouts')->name('instrumentist.payouts');
    Volt::route('instrumentist/payouts/{batch}/voucher', 'payouts.voucher')->name('instrumentist.payouts.voucher');
});

Route::middleware(['auth', 'admin'])->group(function () {
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
});

Route::middleware(['auth', 'superadmin'])->group(function () {
    Volt::route('hospitals', 'hospitals.index')->name('hospitals.index');
    Volt::route('hospitals/create', 'hospitals.create')->name('hospitals.create');
    Volt::route('hospitals/{hospital}/edit', 'hospitals.edit')->name('hospitals.edit');

    Volt::route('users', 'users.index')->name('users.index');
    Volt::route('users/create', 'users.create')->name('users.create');
    Volt::route('users/{user}/edit', 'users.edit')->name('users.edit');
    Volt::route('roles', 'access.roles')->name('roles.index');
    Volt::route('permissions', 'access.permissions')->name('permissions.index');
});
