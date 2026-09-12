<?php

use App\Models\Admission;
use App\Support\PatientAge;

use function Livewire\Volt\{state, mount};

state([
    'admission' => null,
    'tokenValid' => false,
]);

mount(function (Admission $admission) {
    $token = request('token');

    if (! $token || ! hash_equals((string) $admission->qr_token, (string) $token)) {
        abort(403, 'Token de QR inválido.');
    }

    $this->admission = $admission;
    $this->tokenValid = true;
});

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between no-print">
        <flux:heading size="xl">{{ __('Expediente de ingreso') }}</flux:heading>
        <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
    </div>

    @if ($admission)
        <div class="print-area rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6 print:shadow-none print:border-none print:p-0">
            {{-- Cabecera imprimible --}}
            <div class="flex flex-col md:flex-row gap-6 items-start">
                <div class="flex-1 space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="size-14 rounded-full bg-accent text-white grid place-items-center text-xl font-semibold">
                            {{ collect([$admission->patient->primer_nombre, $admission->patient->primer_apellido])->filter()->map(fn($w) => mb_substr($w, 0, 1))->implode('') ?: '?' }}
                        </div>
                        <div>
                            <p class="text-2xl font-semibold">{{ $admission->patient->nombreCompleto() ?: __('Recién nacido/a') }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ App\Enums\AdmissionType::from($admission->tipo_atencion)->label() }}
                                · {{ $admission->completo ? __('Completo') : __('Pendiente de completar') }}
                            </p>
                        </div>
                    </div>

                    @if ($admission->patient->expediente_no)
                        <p class="text-lg">
                            <span class="text-zinc-500">{{ __('Expediente No.') }}</span>
                            <span class="font-bold text-accent">{{ $admission->patient->expediente_no }}</span>
                        </p>
                    @endif
                </div>

                <div class="flex flex-col items-center gap-3">
                    <div class="p-3 bg-white rounded-xl border border-zinc-200 shadow-sm print:shadow-none print:border print:p-2">
                        {!! \App\Support\AdmissionQr::svg($admission, 160) !!}
                    </div>
                    <p class="text-xs text-zinc-500 text-center max-w-[160px] break-words hidden print:block">
                        {{ $admission->qrUrl() }}
                    </p>
                </div>
            </div>

            {{-- Datos del paciente --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                <x-admissions.info label="{{ __('Documento') }}" :value="($admission->patient->id_type ?: 'DPI') . ' ' . ($admission->patient->dpi ?: '')" />
                <x-admissions.info label="{{ __('Fecha de nacimiento') }}" :value="$admission->patient->fecha_nacimiento?->format('d/m/Y')" />
                <x-admissions.info label="{{ __('Edad') }}" :value="$admission->patient->fecha_nacimiento ? PatientAge::from($admission->patient->fecha_nacimiento)->fullFormatted() : null" />
                <x-admissions.info label="{{ __('Sexo') }}" :value="$admission->patient->sexo" />
                <x-admissions.info label="{{ __('Lugar de nacimiento') }}" :value="$admission->patient->lugar_nacimiento" />
                <x-admissions.info label="{{ __('Nacionalidad') }}" :value="$admission->patient->nacionalidad" />
                <x-admissions.info label="{{ __('Estado civil') }}" :value="match ($admission->patient->estado_civil) { 'S' => 'Soltero/a', 'C' => 'Casado/a', 'U' => 'Unido/a', 'D' => 'Divorciado/a', 'V' => 'Viudo/a', default => null }" />
                <x-admissions.info label="{{ __('Teléfono del paciente') }}" :value="$admission->patient->telefono" />
                <x-admissions.info label="{{ __('Teléfono de casa') }}" :value="$admission->patient->telefono_casa" />
            </div>

            @if (! empty($admission->patient->emergency_contacts))
                <div class="space-y-2">
                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Contactos de emergencia') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        @foreach ($admission->patient->emergency_contacts as $contact)
                            <div class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="font-medium">{{ $contact['nombre'] ?? '' }}</div>
                                <div class="text-zinc-500">{{ $contact['telefono'] ?? '' }}</div>
                                @if (! empty($contact['municipio']) || ! empty($contact['departamento']))
                                    <div class="text-zinc-500 text-xs">{{ collect([$contact['municipio'] ?? null, $contact['departamento'] ?? null])->filter()->implode(', ') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <x-admissions.info label="{{ __('Dirección habitual') }}" :value="$admission->patient->direccion_habitual" />
                <x-admissions.info label="{{ __('Municipio') }}" :value="$admission->patient->municipio" />
                <x-admissions.info label="{{ __('Departamento') }}" :value="$admission->patient->departamento" />
                <x-admissions.info label="{{ __('Nombre del padre') }}" :value="$admission->patient->nombre_padre" />
                <x-admissions.info label="{{ __('Nombre de la madre') }}" :value="$admission->patient->nombre_madre" />
                <x-admissions.info label="{{ __('Nombre del cónyuge') }}" :value="$admission->patient->nombre_conyuge" />
            </div>

            {{-- Datos del ingreso --}}
            <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6 space-y-4">
                <flux:heading size="lg">{{ __('Datos del ingreso') }}</flux:heading>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <x-admissions.info label="{{ __('Fecha de ingreso') }}" :value="$admission->fecha_ingreso?->format('d/m/Y')" />
                    <x-admissions.info label="{{ __('Hora') }}" :value="$admission->hora_ingreso" />
                    <x-admissions.info label="{{ __('Sala') }}" :value="$admission->sala_ingreso" />
                    <x-admissions.info label="{{ __('Habitación') }}" :value="$admission->habitacion" />
                    <x-admissions.info label="{{ __('Va a quirófano') }}" :value="$admission->va_a_quirofano ? 'Sí' : 'No'" />
                    <x-admissions.info label="{{ __('Muestra patología') }}" :value="$admission->muestra_patologia ? 'Sí' : 'No'" />
                    <x-admissions.info label="{{ __('Seguro') }}" :value="$admission->tiene_seguro ? ($admission->compania_seguros ?: 'Sí') : 'No'" />
                    <x-admissions.info label="IGSS" :value="$admission->tiene_igss ? 'Sí' : 'No'" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <x-admissions.info label="{{ __('Médico responsable') }}" :value="$admission->medico_responsable" />
                    @if ($admission->medico_colegiado)
                        <x-admissions.info label="{{ __('No. de colegiado') }}" :value="$admission->medico_colegiado" />
                    @endif
                    <x-admissions.info label="{{ __('Póliza') }}" :value="$admission->poliza" />
                    <x-admissions.info label="{{ __('Certificado') }}" :value="$admission->certificado" />
                    <x-admissions.info label="{{ __('Referido por') }}" :value="$admission->referido_por" />
                </div>

                @if ($admission->otras_hospitalizaciones)
                    <x-admissions.text label="{{ __('Otras hospitalizaciones') }}" :value="$admission->otras_hospitalizaciones" />
                @endif

                @if ($admission->impresion_clinica || $admission->diagnostico_final || $admission->complicaciones || $admission->operaciones)
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4 space-y-3">
                        @if ($admission->impresion_clinica)
                            <x-admissions.text label="{{ __('Impresión clínica de ingreso') }}" :value="$admission->impresion_clinica" />
                        @endif
                        @if ($admission->diagnostico_final)
                            <x-admissions.text label="{{ __('Diagnóstico final') }}" :value="$admission->diagnostico_final" />
                        @endif
                        @if ($admission->complicaciones)
                            <x-admissions.text label="{{ __('Complicaciones') }}" :value="$admission->complicaciones" />
                        @endif
                        @if ($admission->operaciones)
                            <x-admissions.text label="{{ __('Operaciones') }}" :value="$admission->operaciones" />
                        @endif
                    </div>
                @endif
            </div>

            {{-- Maternidad --}}
            @if ($admission->patient->sexo === 'F' && ($admission->maternidad_no_hijo || $admission->maternidad_fecha_nacimiento || $admission->maternidad_condiciones_egreso))
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6 space-y-4">
                    <flux:heading size="lg">{{ __('Maternidad') }}</flux:heading>

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <x-admissions.info label="{{ __('No. de hijo') }}" :value="$admission->maternidad_no_hijo" />
                        <x-admissions.info label="{{ __('Fecha de nacimiento') }}" :value="$admission->maternidad_fecha_nacimiento?->format('d/m/Y')" />
                        <x-admissions.info label="{{ __('Hora') }}" :value="$admission->maternidad_hora" />
                        <x-admissions.info label="{{ __('Sexo del recién nacido') }}" :value="$admission->maternidad_sexo" />
                    </div>

                    <x-admissions.text label="{{ __('Condiciones del egreso') }}" :value="$admission->maternidad_condiciones_egreso" />
                </div>
            @endif

            <div class="flex justify-end gap-3 no-print">
                <x-qr-scanner-button />
                <flux:button onclick="window.print()" variant="primary" icon="printer">
                    {{ __('Imprimir') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
