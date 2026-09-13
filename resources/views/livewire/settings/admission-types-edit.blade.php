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

    public function mount(AdmissionType $admissionType): void
    {
        abort_unless(Auth::check(), 401);
        abort_unless((bool) Auth::user()->can('settings.manage'), 403);
        abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
        abort_unless($admissionType->hospital_id === Auth::user()->hospital_id, 403);

        $this->admissionType = $admissionType;
        $this->visibleSections = $admissionType->visible_sections ?? [];
        $this->requiredSections = $admissionType->required_sections ?? [];
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

    private function save(): void
    {
        $this->admissionType->update([
            'visible_sections' => $this->visibleSections,
            'required_sections' => $this->requiredSections,
        ]);
    }
}; ?>

<div class="max-w-3xl mx-auto p-4 space-y-6">
    <flux:heading size="lg">Editar: {{ $admissionType->name }}</flux:heading>

    <div class="space-y-2">
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

    <flux:button :href="route('settings.admission-types')" wire:navigate>Volver</flux:button>
</div>
