@props(['label', 'value' => null])

@if ($value)
    <div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $label }}</p>
        <p class="font-medium">{{ $value }}</p>
    </div>
@endif
