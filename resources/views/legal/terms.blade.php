{{--
    Stub informativo. No constituye asesoría legal. Ver nota al pie visible
    para el usuario final.
--}}
<x-layouts.legal :title="__('Términos de Servicio')">
    <p>
        {{ __('xaCare es una plataforma SaaS de gestión hospitalaria que permite a hospitales y clínicas administrar pacientes, ingresos, personal y otros procesos administrativos y clínicos de su institución.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('1. Cuenta y acceso') }}
    </h2>
    <p>
        {{ __('Cada hospital cliente es responsable de mantener la confidencialidad de las credenciales de su cuenta y de las cuentas de su personal, así como de toda actividad que ocurra bajo dichas cuentas.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('2. Responsabilidad sobre los datos clínicos') }}
    </h2>
    <p>
        {{ __('Los datos clínicos y de pacientes ingresados en xaCare pertenecen y son responsabilidad exclusiva del hospital cliente. xaCare actúa únicamente como proveedor de la plataforma tecnológica y no reclama propiedad alguna sobre dichos datos.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('3. Disponibilidad del servicio') }}
    </h2>
    <p>
        {{ __('El servicio se ofrece "tal cual" ("as-is"), sin garantías de ningún tipo, expresas o implícitas, incluyendo disponibilidad ininterrumpida o ausencia de errores.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('4. Límite de responsabilidad') }}
    </h2>
    <p>
        {{ __('En la medida permitida por la ley aplicable, xaCare no será responsable por daños indirectos, incidentales o consecuentes derivados del uso o la imposibilidad de uso del servicio.') }}
    </p>

    <p class="text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Última actualización: :fecha', ['fecha' => '5 de septiembre de 2026']) }}
    </p>

    <footer class="border-t border-zinc-200 pt-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
        {{ __('Este documento es informativo y no constituye asesoría legal.') }}
    </footer>
</x-layouts.legal>
