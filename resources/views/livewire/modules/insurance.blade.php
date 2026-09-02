<?php

use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{mount};

mount(function () {
    abort_unless(Auth::check(), 403);
});

?>

<div class="max-w-2xl mx-auto p-4 space-y-6">
    <flux:heading size="xl">{{ __('Seguros') }}</flux:heading>
    <flux:subheading>{{ __('Módulo Pro') }}</flux:subheading>

    <flux:callout icon="shield-check" heading="{{ __('Módulo de seguros — próximamente') }}">
        {{ __('Este hospital tiene el módulo de seguros en su plan. El flujo de aseguradoras, calculadora y PDFs se construirá como paquete PRO; esta pantalla confirma el gating de facturación.') }}
    </flux:callout>
</div>
