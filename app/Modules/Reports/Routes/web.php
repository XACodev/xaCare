<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::prefix('reports')->name('reports.')->middleware(['auth', 'admin', 'hospital.subscribed'])->group(function () {
    Volt::route('procedures', 'reports.procedures')->name('procedures');
    Volt::route('payouts', 'reports.payouts')->name('payouts');
    Volt::route('distribution', 'reports.distribution')->name('distribution');
});

Route::prefix('platform/reports')->name('platform.reports.')->middleware(['auth', 'platform-admin'])->group(function () {
    Volt::route('hospitals', 'platform.reports.hospitals')->name('hospitals');
    Volt::route('procedures', 'platform.reports.procedures')->name('procedures');
    Volt::route('revenue', 'platform.reports.revenue')->name('revenue');
});
