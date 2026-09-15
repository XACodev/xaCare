@props([
    'href',
    'label',
])

{{-- Lenguaje visual de los mockups de ingreso (1c): «← etiqueta» en accent, 15px — no un botón ghost de navegador. --}}
<div {{ $attributes->class('lg:hidden') }} data-test="mobile-back">
    <a href="{{ $href }}" wire:navigate
        class="inline-flex items-center gap-1 py-1 text-[15px] font-medium text-accent">
        <span aria-hidden="true">←</span>
        {{ $label }}
    </a>
</div>
