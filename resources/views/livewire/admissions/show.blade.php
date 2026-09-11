<?php

use App\Models\Admission;

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

<div class="max-w-3xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Expediente de ingreso') }}</flux:heading>
        <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
    </div>

    @if ($admission)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
            <div class="flex flex-col md:flex-row gap-6">
                <div class="flex-1 space-y-4">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Paciente') }}</p>
                        <p class="text-xl font-semibold">{{ $admission->patient->nombreCompleto() }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Tipo de atención') }}</p>
                            <p class="font-medium">{{ \App\Enums\AdmissionType::from($admission->tipo_atencion)->label() }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Fecha de ingreso') }}</p>
                            <p class="font-medium">{{ $admission->fecha_ingreso?->format('d/m/Y') }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Hora') }}</p>
                            <p class="font-medium">{{ $admission->hora_ingreso }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500 dark:text-zinc-400">{{ __('Sala') }}</p>
                            <p class="font-medium">{{ $admission->sala_ingreso ?: '—' }}</p>
                        </div>
                    </div>

                    @if ($admission->impresion_clinica)
                        <div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Impresión clínica') }}</p>
                            <p class="text-sm whitespace-pre-line">{{ $admission->impresion_clinica }}</p>
                        </div>
                    @endif

                    @if ($admission->medico_responsable)
                        <div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Médico responsable') }}</p>
                            <p class="font-medium">{{ $admission->medico_responsable }}</p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col items-center gap-4">
                    <div class="p-4 bg-white rounded-xl border border-zinc-200 shadow-sm">
                        {!! \App\Support\AdmissionQr::svg($admission, 200) !!}
                    </div>
                    <flux:button onclick="window.print()" variant="outline" icon="printer">
                        {{ __('Imprimir QR') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
