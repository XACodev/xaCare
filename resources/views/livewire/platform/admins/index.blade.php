<?php

use Livewire\Volt\Component;

new class extends Component {
    public function layout(): mixed
    {
        return view('components.layouts.platform', ['title' => __('Administradores de plataforma')]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Administradores de plataforma') }}</flux:heading>
</div>
