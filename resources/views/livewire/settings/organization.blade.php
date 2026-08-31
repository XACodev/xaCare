<?php

use App\Models\OrganizationSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

use function Livewire\Volt\{state, mount, rules, uses};

uses(WithFileUploads::class);

state([
    'org_name' => '',
    'voucher_legend' => '',
    'logo' => null,
    'logo_url' => null,
    'success' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $s = OrganizationSetting::current();

    $this->org_name = $s->org_name;
    $this->voucher_legend = $s->voucher_legend;
    $this->logo_url = $s->logoUrl();
});

rules([
    'org_name' => ['required', 'string', 'max:255'],
    'voucher_legend' => ['required', 'string', 'max:1000'],
    'logo' => ['nullable', 'image', 'max:2048'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $data = $this->validate();

    $settings = OrganizationSetting::current();

    unset($data['logo']);

    if ($this->logo) {
        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $data['logo_path'] = $this->logo->store('org-logos', 'public');
    }

    $settings->update($data);

    $this->logo = null;
    $this->logo_url = $settings->fresh()->logoUrl();
    $this->success = __('Settings saved.');
};

$removeLogo = function () {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $settings = OrganizationSetting::current();

    if ($settings->logo_path) {
        Storage::disk('public')->delete($settings->logo_path);
        $settings->update(['logo_path' => null]);
    }

    $this->logo = null;
    $this->logo_url = null;
    $this->success = __('Settings saved.');
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('General Settings') }}</flux:heading>
        <flux:subheading>{{ __('Organization data used on printed documents') }}</flux:subheading>
    </div>

    @if($success)
        <div
            class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-300">
            {{ $success }}
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input label="{{ __('Organization Name') }}" wire:model.live="org_name" clearable />

        <flux:textarea label="{{ __('Voucher Legend') }}" wire:model.live="voucher_legend" rows="3" />

        <div class="space-y-2">
            <flux:label>{{ __('Organization Logo') }}</flux:label>
            <flux:description>
                {{ __('Shown on the payment voucher header. Leave empty to print the voucher without a logo.') }}
            </flux:description>

            <div class="flex items-center gap-4">
                @if($logo)
                    <img src="{{ $logo->temporaryUrl() }}" alt="{{ __('Logo preview') }}"
                        class="size-16 rounded-lg border border-zinc-200 dark:border-zinc-700 object-contain bg-white p-1" />
                @elseif($logo_url)
                    <img src="{{ $logo_url }}" alt="{{ __('Organization Logo') }}"
                        class="size-16 rounded-lg border border-zinc-200 dark:border-zinc-700 object-contain bg-white p-1" />
                @else
                    <div
                        class="size-16 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 flex items-center justify-center text-zinc-400">
                        <flux:icon.photo class="size-6" />
                    </div>
                @endif

                <div class="flex-1 space-y-1">
                    <input type="file" wire:model="logo" accept="image/*"
                        class="block w-full text-sm text-zinc-600 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-zinc-100 file:text-zinc-700 dark:file:bg-zinc-800 dark:file:text-zinc-200 hover:file:bg-zinc-200 dark:hover:file:bg-zinc-700" />
                    <flux:error name="logo" />
                </div>

                @if($logo_url && !$logo)
                    <flux:button wire:click="removeLogo" variant="ghost" size="sm">
                        {{ __('Remove') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="pt-2 flex justify-end">
            <flux:button wire:click="save" variant="primary">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</div>
