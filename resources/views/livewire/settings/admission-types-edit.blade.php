<?php

use App\Models\AdmissionType;
use App\Models\AdmissionTypeCustomField;
use App\Support\AdmissionFormSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component
{
    #[Locked]
    public AdmissionType $admissionType;

    public array $visibleSections = [];

    public array $requiredSections = [];

    public string $businessHourTypeSlug = '';

    public string $afterHoursTypeSlug = '';

    public bool $businessHourSlugsSaved = false;

    public function mount(AdmissionType $admissionType): void
    {
        abort_unless(Auth::check(), 401);
        abort_unless((bool) Auth::user()->can('settings.manage'), 403);
        abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
        abort_unless($admissionType->hospital_id === Auth::user()->hospital_id, 403);

        $this->admissionType = $admissionType;
        $this->visibleSections = $admissionType->visible_sections ?? [];
        $this->requiredSections = $admissionType->required_sections ?? [];
        $this->businessHourTypeSlug = $admissionType->business_hour_type_slug ?? '';
        $this->afterHoursTypeSlug = $admissionType->after_hours_type_slug ?? '';
    }

    /** @return array<int, array{value: string, label: string}> */
    public function sections(): array
    {
        return AdmissionFormSection::options();
    }

    public function customFields(): Collection
    {
        return $this->admissionType->customFields()->where('active', true)->orderBy('sort_order')->get();
    }

    public function toggleSection(string $section, bool $visible): void
    {
        abort_unless(AdmissionFormSection::tryFrom($section) !== null, 422);

        $this->visibleSections = $visible
            ? array_values(array_unique([...$this->visibleSections, $section]))
            : array_values(array_diff($this->visibleSections, [$section]));

        if (! $visible) {
            $this->requiredSections = array_values(array_diff($this->requiredSections, [$section]));
        }

        $this->save();
    }

    public function toggleRequired(string $section, bool $required): void
    {
        abort_unless(AdmissionFormSection::tryFrom($section) !== null, 422);
        abort_unless(in_array($section, $this->visibleSections, true), 422);

        $this->requiredSections = $required
            ? array_values(array_unique([...$this->requiredSections, $section]))
            : array_values(array_diff($this->requiredSections, [$section]));

        $this->save();
    }

    public function deleteCustomField(int $fieldId): void
    {
        $field = AdmissionTypeCustomField::withoutGlobalScopes()->findOrFail($fieldId);
        abort_unless($field->hospital_id === $this->admissionType->hospital_id, 403);

        // No se elimina físicamente: las respuestas históricas (admission_custom_field_values)
        // quedarían huérfanas por el cascadeOnDelete. Se desactiva en su lugar.
        $field->update(['active' => false]);
    }

    public function saveBusinessHourSlugs(): void
    {
        abort_unless((bool) Auth::user()->can('settings.manage'), 403);

        $this->validate([
            'businessHourTypeSlug' => ['nullable', 'string', 'max:255'],
            'afterHoursTypeSlug' => ['nullable', 'string', 'max:255'],
        ]);

        $hospitalId = $this->admissionType->hospital_id;
        $existingSlugs = AdmissionType::query()
            ->where('hospital_id', $hospitalId)
            ->pluck('slug')
            ->all();

        if (filled($this->businessHourTypeSlug) && ! in_array($this->businessHourTypeSlug, $existingSlugs, true)) {
            $this->addError('businessHourTypeSlug', __('El slug de tipo hábil no existe en este hospital.'));

            return;
        }

        if (filled($this->afterHoursTypeSlug) && ! in_array($this->afterHoursTypeSlug, $existingSlugs, true)) {
            $this->addError('afterHoursTypeSlug', __('El slug de tipo inhábil no existe en este hospital.'));

            return;
        }

        $this->admissionType->update([
            'business_hour_type_slug' => $this->businessHourTypeSlug ?: null,
            'after_hours_type_slug' => $this->afterHoursTypeSlug ?: null,
        ]);

        $this->businessHourSlugsSaved = true;
    }

    private function save(): void
    {
        $this->admissionType->update([
            'visible_sections' => $this->visibleSections,
            'required_sections' => $this->requiredSections,
        ]);
    }
}; ?>

<div class="max-w-3xl mx-auto p-4 space-y-8">
    <x-mobile-back :href="route('settings.admission-types')" :label="__('Tipos de ingreso')" />
    <flux:heading size="lg">Editar: {{ $admissionType->name }}</flux:heading>

    <div class="space-y-2">
        <flux:heading size="md">{{ __('Secciones del formulario') }}</flux:heading>
        @foreach ($this->sections() as $section)
            <div class="flex items-center gap-4">
                <flux:checkbox
                    :checked="in_array($section['value'], $visibleSections, true)"
                    wire:click="toggleSection('{{ $section['value'] }}', {{ (int) ! in_array($section['value'], $visibleSections, true) }})"
                    label="{{ $section['label'] }}"
                />
                <flux:checkbox
                    :checked="in_array($section['value'], $requiredSections, true)"
                    :disabled="! in_array($section['value'], $visibleSections, true)"
                    wire:click="toggleRequired('{{ $section['value'] }}', {{ (int) ! in_array($section['value'], $requiredSections, true) }})"
                    label="Obligatoria"
                />
            </div>
        @endforeach
    </div>

    @if (Auth::user()->hospital?->hasFeature('admissions_business_hours'))
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
            <div>
                <flux:heading size="md">{{ __('Resolución de horario hábil/inhábil') }}</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Si configuras estos slugs, este tipo aparecerá como opción base en el selector de nuevo ingreso y el sistema elegirá automáticamente la variante según la fecha/hora.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="businessHourTypeSlug" label="{{ __('Tipo en horario hábil (slug)') }}" placeholder="ej. emergencia-habil" />
                <flux:input wire:model="afterHoursTypeSlug" label="{{ __('Tipo en horario inhábil (slug)') }}" placeholder="ej. emergencia-inhabil" />
            </div>
            @error('businessHourTypeSlug') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            @error('afterHoursTypeSlug') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            @if ($businessHourSlugsSaved)
                <flux:callout variant="success" icon="check-circle" heading="{{ __('Mapeo guardado.') }}" />
            @endif

            <div class="flex justify-end">
                <flux:button wire:click="saveBusinessHourSlugs" variant="primary">{{ __('Guardar mapeo') }}</flux:button>
            </div>
        </div>
    @endif

    <div class="space-y-2">
        <flux:heading size="md">Campos personalizados</flux:heading>
        <div class="space-y-2">
            @foreach ($this->customFields() as $field)
                <div class="flex items-center justify-between">
                    <span>{{ $field->label }} ({{ $field->field_type }}, paso {{ $field->step }})</span>
                    <flux:button wire:click="deleteCustomField({{ $field->id }})" variant="danger" size="sm">
                        Desactivar
                    </flux:button>
                </div>
            @endforeach
        </div>
    </div>

    <flux:button :href="route('settings.admission-types')" wire:navigate class="hidden lg:inline-flex">{{ __('Volver') }}</flux:button>
</div>
