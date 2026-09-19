<?php

use App\Models\BusinessHoliday;
use App\Models\BusinessHour;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state([
    'days' => [],
    'holidays' => [],
    'newHolidayDate' => '',
    'newHolidayName' => '',
    'successMessage' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');

    $hospitalId = Auth::user()->hospital_id;

    $dayLabels = [
        0 => __('Lunes'),
        1 => __('Martes'),
        2 => __('Miércoles'),
        3 => __('Jueves'),
        4 => __('Viernes'),
        5 => __('Sábado'),
        6 => __('Domingo'),
    ];

    $existing = BusinessHour::query()
        ->where('hospital_id', $hospitalId)
        ->get()
        ->keyBy('day_of_week');

    $this->days = collect(range(0, 6))->map(function (int $dayOfWeek) use ($dayLabels, $existing) {
        $record = $existing->get($dayOfWeek);

        return [
            'day_of_week' => $dayOfWeek,
            'label' => $dayLabels[$dayOfWeek],
            'is_business_day' => $record?->is_business_day ?? ($dayOfWeek < 5),
            'start_time' => $record?->start_time?->format('H:i') ?? '07:00',
            'end_time' => $record?->end_time?->format('H:i') ?? '18:00',
        ];
    })->all();

    $this->loadHolidays();
});

$loadHolidays = function () {
    $this->holidays = BusinessHoliday::query()
        ->where('hospital_id', Auth::user()->hospital_id)
        ->orderBy('date')
        ->get()
        ->map(fn ($h) => [
            'id' => $h->id,
            'date' => $h->date->format('Y-m-d'),
            'name' => $h->name,
        ])
        ->all();
};

$saveDays = function () {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $hospitalId = Auth::user()->hospital_id;

    foreach ($this->days as $day) {
        BusinessHour::updateOrCreate(
            ['hospital_id' => $hospitalId, 'day_of_week' => $day['day_of_week']],
            [
                'is_business_day' => (bool) ($day['is_business_day'] ?? false),
                'start_time' => ($day['is_business_day'] ?? false) ? $day['start_time'] : null,
                'end_time' => ($day['is_business_day'] ?? false) ? $day['end_time'] : null,
            ]
        );
    }

    $this->successMessage = __('Horario semanal guardado.');
};

$addHoliday = function () {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $this->validate([
        'newHolidayDate' => ['required', 'date', 'date_format:Y-m-d'],
        'newHolidayName' => ['nullable', 'string', 'max:255'],
    ]);

    BusinessHoliday::updateOrCreate(
        ['hospital_id' => Auth::user()->hospital_id, 'date' => $this->newHolidayDate],
        ['name' => $this->newHolidayName ?: null]
    );

    $this->newHolidayDate = '';
    $this->newHolidayName = '';
    $this->successMessage = null;
    $this->loadHolidays();
};

$deleteHoliday = function (int $id) {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $holiday = BusinessHoliday::query()
        ->where('hospital_id', Auth::user()->hospital_id)
        ->where('id', $id)
        ->first();

    if ($holiday) {
        $holiday->delete();
    }

    $this->loadHolidays();
};

?>

<div class="max-w-3xl mx-auto p-4 space-y-6">
    <div class="mb-4 space-y-1">
        <flux:heading size="xl">{{ __('Horarios hábiles') }}</flux:heading>
        <flux:subheading>{{ __('Configura qué días y horarios se consideran hábiles para la clasificación automática de ingresos.') }}</flux:subheading>
        <x-back-link :fallback="route('settings.organization')" />
    </div>

    @if ($successMessage)
        <flux:callout variant="success" icon="check-circle" heading="{{ $successMessage }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Horario semanal') }}</flux:heading>
            <flux:button wire:click="saveDays" variant="primary">{{ __('Guardar horario') }}</flux:button>
        </div>

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($days as $index => $day)
                <div class="py-4 grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
                    <div class="sm:col-span-4 flex items-center gap-3">
                        <flux:checkbox wire:model="days.{{ $index }}.is_business_day" label="{{ $day['label'] }}" />
                    </div>
                    <div class="sm:col-span-4">
                        <flux:input type="time" wire:model="days.{{ $index }}.start_time" label="{{ __('Inicio') }}" :disabled="! $day['is_business_day']" />
                    </div>
                    <div class="sm:col-span-4">
                        <flux:input type="time" wire:model="days.{{ $index }}.end_time" label="{{ __('Fin') }}" :disabled="! $day['is_business_day']" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:heading size="lg">{{ __('Días inhábiles / feriados') }}</flux:heading>

        <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-end">
            <div class="sm:col-span-4">
                <flux:input type="date" wire:model="newHolidayDate" label="{{ __('Fecha') }}" />
            </div>
            <div class="sm:col-span-6">
                <flux:input wire:model="newHolidayName" label="{{ __('Nombre (opcional)') }}" placeholder="{{ __('Ej. Año Nuevo') }}" />
            </div>
            <div class="sm:col-span-2">
                <flux:button wire:click="addHoliday" variant="primary" class="w-full">{{ __('Agregar') }}</flux:button>
            </div>
        </div>
        @error('newHolidayDate') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($holidays as $holiday)
                <div class="py-3 flex items-center justify-between">
                    <div>
                        <div class="font-medium">{{ \Carbon\Carbon::parse($holiday['date'])->format('d/m/Y') }}</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $holiday['name'] ?: __('Feriado') }}</div>
                    </div>
                    <flux:button size="sm" variant="danger" wire:click="deleteHoliday({{ $holiday['id'] }})" wire:confirm="{{ __('¿Eliminar este feriado?') }}">
                        {{ __('Eliminar') }}
                    </flux:button>
                </div>
            @empty
                <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No hay feriados configurados.') }}</p>
            @endforelse
        </div>
    </div>
</div>
