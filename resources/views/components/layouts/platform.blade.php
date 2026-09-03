<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-slate-50 dark:bg-zinc-950">
    <x-layouts.platform.sidebar :title="$title ?? null">
        {{ $slot }}
    </x-layouts.platform.sidebar>

    @fluxScripts
</body>

</html>
