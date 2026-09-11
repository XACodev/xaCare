<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-night">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col justify-between overflow-hidden bg-accent p-12 text-white lg:flex">
                <div class="absolute inset-0 grid grid-cols-6 grid-rows-7 opacity-10" aria-hidden="true">
                    @for ($i = 0; $i < 42; $i++)
                        <div class="grid place-items-center">
                            <svg width="48" height="48" viewBox="0 0 32 32">
                                <rect x="5.5" y="5.5" width="9" height="9" rx="2.5" fill="#fff" transform="rotate(45 16 16)" />
                                <rect x="17.5" y="5.5" width="9" height="9" rx="2.5" fill="#fff" transform="rotate(45 16 16)" />
                                <rect x="5.5" y="17.5" width="9" height="9" rx="2.5" fill="#fff" transform="rotate(45 16 16)" />
                                <rect x="17.5" y="17.5" width="9" height="9" rx="2.5" fill="#fff" transform="rotate(45 16 16)" />
                            </svg>
                        </div>
                    @endfor
                </div>

                <a href="{{ route('home') }}" class="relative z-20 flex items-center gap-3" wire:navigate>
                    <x-app-logo-icon class="size-9" />
                    <span class="text-xl font-semibold tracking-tight">xaCare</span>
                </a>

                <div class="relative z-20 grid max-w-md gap-4">
                    <h2 class="text-4xl font-semibold leading-tight tracking-tight">
                        {{ __('Ingresos en minutos, expediente completo después.') }}
                    </h2>
                    <p class="text-base leading-relaxed opacity-90">
                        {{ __('Pacientes, cirugías, pagos y seguros en un solo lugar — para hospitales y clínicas de cualquier tamaño.') }}
                    </p>
                </div>

                <div class="relative z-20 text-sm opacity-80">
                    {{ __('Plataforma de gestión hospitalaria y clínica') }}
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                    <a href="{{ route('home') }}" class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <x-app-logo-icon class="size-9" />
                        <span class="sr-only">xaCare</span>
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
