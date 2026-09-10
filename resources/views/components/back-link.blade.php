@props(['fallback', 'label' => null])
@php
    $previous = session('_previous.url');
    $target = ($previous && $previous !== request()->fullUrl() && parse_url($previous, PHP_URL_HOST) === request()->getHost())
        ? $previous
        : $fallback;
@endphp
<flux:button href="{{ $target }}" variant="subtle" size="sm" icon="arrow-left">
    {{ $label ?? __('Back') }}
</flux:button>
