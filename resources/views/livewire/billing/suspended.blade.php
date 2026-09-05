<?php

use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{layout, mount};

layout('components.layouts.app');

mount(function () {
    abort_unless(Auth::check(), 403);
});

?>

<div class="flex min-h-[60vh] flex-col items-center justify-center gap-4 px-4 text-center">
    <flux:icon.lock-closed class="size-12 text-zinc-400" />

    <flux:heading size="xl">{{ __('Suscripción suspendida') }}</flux:heading>

    <flux:subheading class="max-w-md">
        {{ __('Tu suscripción está suspendida. Contacta a soporte para reactivar el acceso de tu hospital.') }}
    </flux:subheading>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <flux:button type="submit" variant="ghost">{{ __('Log Out') }}</flux:button>
    </form>
</div>
