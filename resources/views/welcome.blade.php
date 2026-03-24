<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full bg-slate-50 dark:bg-slate-900 transition-colors duration-300">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'xaCare') }} - {{ config('app.description', 'Sistema de Gestión Hospitalaria') }}</title>

    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full font-sans antialiased text-slate-600 dark:text-slate-400 transition-colors duration-300">
    <div class="relative min-h-screen flex flex-col">
        <!-- Navbar -->
        <nav class="relative z-10 px-6 py-4 flex justify-between items-center max-w-7xl mx-auto w-full">
            <div class="flex items-center gap-2">
                <div class="bg-teal-600 p-2 rounded-lg shadow-lg shadow-teal-600/20">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-6 h-6 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <span
                    class="text-xl font-bold text-slate-800 dark:text-slate-100 tracking-tight transition-colors">{{ config('app.name', 'xaCare') }}</span>
            </div>

            <div class="flex items-center gap-4">
                <!-- Theme Toggle -->
                <button onclick="toggleTheme()"
                    class="p-2 rounded-lg text-slate-500 hover:text-teal-600 dark:text-slate-400 dark:hover:text-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-500/50 transition-colors"
                    aria-label="Toggle Dark Mode">
                    <!-- Sun Icon (Hidden manually when dark) -->
                    <svg id="theme-toggle-light-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="w-6 h-6 hidden dark:block">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                    <!-- Moon Icon (Hidden manually when light) -->
                    <svg id="theme-toggle-dark-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor" class="w-6 h-6 block dark:hidden">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </button>

                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}"
                            class="text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                            {{ __('Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                            class="text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-teal-600 dark:hover:text-teal-400 transition-colors">
                            {{ __('Login') }}
                        </a>
                    @endauth
                @endif
            </div>
        </nav>

        <!-- Hero Section -->
        <main class="grow flex items-center justify-center relative overflow-hidden">
            <!-- Background decorative blobs -->
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
                <div
                    class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-teal-100 dark:bg-teal-900/20 blur-3xl opacity-60 dark:opacity-40 mix-blend-multiply dark:mix-blend-screen transition-colors duration-500">
                </div>
                <div
                    class="absolute top-[20%] -right-[10%] w-[40%] h-[40%] rounded-full bg-blue-100 dark:bg-blue-900/20 blur-3xl opacity-60 dark:opacity-40 mix-blend-multiply dark:mix-blend-screen transition-colors duration-500">
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-6 py-12 lg:py-24 grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left space-y-8">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 dark:bg-teal-900/30 border border-teal-100 dark:border-teal-800 text-teal-700 dark:text-teal-300 text-xs font-semibold uppercase tracking-wide transition-colors">
                        <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                        Sistema Hospitalario
                    </div>
                    <h1
                        class="text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white leading-tight transition-colors">
                        Control Quirúrgico <br>
                        <span class="text-teal-600 dark:text-teal-400">Automatizado y Preciso</span>
                    </h1>
                    <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto lg:mx-0 transition-colors">
                        Olvídate del papel. Gestiona procedimientos, automatiza pagos a instrumentistas y genera
                        vouchers con validez administrativa en segundos.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}"
                                class="inline-flex justify-center items-center px-6 py-3 text-base font-medium text-white bg-teal-600 hover:bg-teal-700 dark:bg-teal-600 dark:hover:bg-teal-500 rounded-lg shadow-lg shadow-teal-600/20 transition-all transform hover:-translate-y-0.5">
                                {{ __('Ingresar al Sistema') }}
                            </a>
                        @endif
                    </div>

                    <!-- Stats / Features Mini Grid -->
                    <div
                        class="pt-8 border-t border-slate-200 dark:border-slate-800 grid grid-cols-3 gap-4 text-center lg:text-left transition-colors">
                        <div>
                            <p class="text-2xl font-bold text-slate-800 dark:text-slate-200 transition-colors">100%</p>
                            <p class="text-sm text-slate-500 dark:text-slate-500">Digital</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-800 dark:text-slate-200 transition-colors">24/7</p>
                            <p class="text-sm text-slate-500 dark:text-slate-500">Acceso</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-800 dark:text-slate-200 transition-colors">Auto</p>
                            <p class="text-sm text-slate-500 dark:text-slate-500">Cálculos</p>
                        </div>
                    </div>
                </div>

                <!-- Illustration / Abstract Representation -->
                <div class="relative hidden lg:block">
                    <div
                        class="relative bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700 transform rotate-2 hover:rotate-0 transition-all duration-500">
                        <div class="flex items-center gap-4 mb-6">
                            <div
                                class="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xl transition-colors">
                                👩‍⚕️</div>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-slate-100 transition-colors">Procedimiento
                                    #4829</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400 transition-colors">Videocirugía •
                                    2h 15m</p>
                            </div>
                            <div class="ml-auto text-teal-600 dark:text-teal-400 font-bold transition-colors">+ Q300.00
                            </div>
                        </div>

                        <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-4 mb-4 space-y-3 transition-colors">
                            <div class="h-2 w-3/4 bg-slate-200 dark:bg-slate-600 rounded"></div>
                            <div class="h-2 w-1/2 bg-slate-200 dark:bg-slate-600 rounded"></div>
                        </div>

                        <div class="flex justify-between items-center text-sm">
                            <span
                                class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-200 px-2 py-1 rounded transition-colors">Pending</span>
                            <span class="text-slate-400 dark:text-slate-500">Hace 10 min</span>
                        </div>
                    </div>

                    <div
                        class="absolute -bottom-6 -right-6 -z-10 bg-teal-600 dark:bg-teal-500 p-6 rounded-2xl shadow-lg w-full h-full opacity-10 dark:opacity-5 transition-colors">
                    </div>
                </div>
            </div>
        </main>

        <footer class="py-6 text-center text-sm text-slate-500 dark:text-slate-600 transition-colors">
            &copy; {{ date('Y') }} {{ config('app.name', 'xaCare') }}. {{ __('All rights reserved') }}.
        </footer>
    </div>

    <script>
        function toggleTheme() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
        }
    </script>
</body>

</html>