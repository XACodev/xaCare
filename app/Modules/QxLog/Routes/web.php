<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'hospital.subscribed', 'hospital.feature:qxlog'])->group(function () {
    Volt::route('procedures/create', 'qxlog.procedures.create')->name('procedures.create');

    Volt::route('instrumentist/payouts', 'qxlog.instrumentist.payouts')->name('instrumentist.payouts');
    Volt::route('instrumentist/payouts/{batch}/voucher', 'qxlog.payouts.voucher')->name('instrumentist.payouts.voucher');
});

Route::middleware(['auth', 'admin', 'hospital.subscribed', 'hospital.feature:qxlog'])->group(function () {
    Volt::route('payouts/create', 'qxlog.payouts.create')->name('payouts.create');
    Volt::route('payouts/{batch}/voucher', 'qxlog.payouts.voucher')->name('payouts.voucher');
    Volt::route('payouts', 'qxlog.payouts.index')->name('payouts.index');

    Volt::route('procedures', 'qxlog.procedures.index')->name('procedures.index');
    Volt::route('procedures/{procedure}/edit', 'qxlog.procedures.edit')->name('procedures.edit');

    Volt::route('pricing/settings', 'qxlog.pricing.settings')->name('pricing.settings');
    Volt::route('pricing/instrumentists', 'qxlog.pricing.instrumentist')->name('pricing.instrumentists');
});
