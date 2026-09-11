<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth', 'hospital.subscribed', 'hospital.feature:qxlog'])->group(function () {
    Volt::route('procedures/create', 'qxlog.procedures.create')->name('procedures.create');

    Volt::route('instrumentist/payouts', 'qxlog.instrumentist.payouts')->name('instrumentist.payouts');
    Volt::route('instrumentist/payouts/{batch}/voucher', 'qxlog.payouts.voucher')->name('instrumentist.payouts.voucher');

    Volt::route('surgeries/create', 'qxlog.surgeries.schedule')->name('surgeries.schedule.create');
    Volt::route('surgeries/{surgery}/edit', 'qxlog.surgeries.schedule')->name('surgeries.schedule.edit');
    Volt::route('surgeries', 'qxlog.surgeries.board')->name('surgeries.board');
});

Route::middleware(['web', 'auth', 'admin', 'hospital.subscribed', 'hospital.feature:qxlog'])->group(function () {
    Volt::route('payouts/create', 'qxlog.payouts.create')->name('payouts.create');
    Volt::route('payouts/{batch}/voucher', 'qxlog.payouts.voucher')->name('payouts.voucher');
    Volt::route('payouts', 'qxlog.payouts.index')->name('payouts.index');

    Volt::route('procedures', 'qxlog.procedures.index')->name('procedures.index');
    Volt::route('procedures/{procedure}/edit', 'qxlog.procedures.edit')->name('procedures.edit');

    Volt::route('pricing/settings', 'qxlog.pricing.settings')->name('pricing.settings');
    Volt::route('pricing/instrumentists', 'qxlog.pricing.instrumentist')->name('pricing.instrumentists');
    Volt::route('pricing/procedure-types', 'qxlog.pricing.procedure-types')->name('pricing.procedure-types');

    // URI 'settings/surgical-roles' (no 'settings/roles') a proposito: esa URI ya la usa
    // routes/web.php para settings.roles.index (roles Spatie/RBAC "Custom", sin relacion con
    // este catalogo de SurgicalRole de QxLog). El nombre de ruta si es `settings.roles` per
    // el contrato de este plan.
    Volt::route('settings/surgical-roles', 'qxlog.settings.roles')->name('settings.roles');
    Volt::route('settings/statuses', 'qxlog.settings.statuses')->name('settings.statuses');
    Volt::route('settings/rooms', 'qxlog.settings.rooms')->name('settings.rooms');
});
