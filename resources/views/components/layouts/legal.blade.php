<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    <div class="flex min-h-svh flex-col gap-6 p-6 md:p-10">
        <main class="mx-auto w-full max-w-3xl flex-1">
            <div class="mb-6">
                <a href="{{ route('home') }}" class="text-sm text-zinc-500 hover:underline dark:text-zinc-400"
                    wire:navigate>
                    &larr; {{ __('Volver al inicio') }}
                </a>
            </div>

            <article class="space-y-6 text-zinc-700 dark:text-zinc-300">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ $title ?? '' }}
                </h1>

                {{ $slot }}
            </article>
        </main>
    </div>

    @fluxScripts
</body>

</html>
