{{--
    Stub informativo. No constituye asesoría legal. Ver nota al pie visible
    para el usuario final.
--}}
<x-layouts.legal :title="__('Política de Privacidad')">
    <p>
        {{ __('En xaCare nos tomamos en serio la privacidad de la información que procesamos. Esta política describe, de forma general, qué datos recolectamos y cómo los usamos.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('1. Qué datos recolectamos') }}
    </h2>
    <p>
        {{ __('Recolectamos datos de cuenta (nombre, correo electrónico, hospital al que pertenece el usuario) y metadatos de uso de la plataforma (accesos, acciones administrativas, registros técnicos) necesarios para operar y mejorar el servicio.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('2. Datos clínicos de pacientes') }}
    </h2>
    <p>
        {{ __('Los datos clínicos de pacientes que el hospital cliente ingresa en xaCare son propiedad y responsabilidad exclusiva de dicho hospital. xaCare no vende, comparte ni utiliza esos datos con fines distintos a prestar el servicio contratado por el hospital.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('3. Finalidad del tratamiento') }}
    </h2>
    <p>
        {{ __('Usamos los datos de cuenta y metadatos de uso para operar la plataforma, brindar soporte, garantizar la seguridad del servicio y cumplir obligaciones legales.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('4. Terceros') }}
    </h2>
    <p>
        {{ __('No compartimos datos con terceros, salvo cuando exista una obligación legal que así lo exija.') }}
    </p>

    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">
        {{ __('5. Ejercicio de derechos') }}
    </h2>
    <p>
        {{ __('Para consultas o para ejercer derechos sobre tus datos personales, contáctanos a través de :correo.', ['correo' => 'privacidad@xacare.com']) }}
    </p>

    <p class="text-sm text-zinc-500 dark:text-zinc-400">
        {{ __('Última actualización: :fecha', ['fecha' => '5 de septiembre de 2026']) }}
    </p>

    <footer class="border-t border-zinc-200 pt-4 text-xs text-zinc-500 dark:border-zinc-800 dark:text-zinc-500">
        {{ __('Este documento es informativo y no constituye asesoría legal.') }}
    </footer>
</x-layouts.legal>
