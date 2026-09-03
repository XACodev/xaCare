<?php

use App\Models\OrganizationSetting;
use App\Support\ImageCompressor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;

use function Livewire\Volt\{state, mount, rules, uses};

uses(WithFileUploads::class);

state([
    'org_name' => '',
    'voucher_legend' => '',
    'logo' => null,
    'logo_url' => null,
    'success' => null,
    'hospital' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $s = OrganizationSetting::current();

    $this->org_name = $s->org_name;
    $this->voucher_legend = $s->voucher_legend;
    $this->logo_url = $s->logoUrl();

    // Solo lectura: el plan/estado de suscripción lo asigna el super admin
    // (es el ciclo de vida del cliente que paga), pero el admin de hospital debe
    // poder ver su propio estado sin tener que preguntarle a nadie.
    $this->hospital = Auth::user()->hospital;
});

rules([
    'org_name' => ['required', 'string', 'max:255'],
    'voucher_legend' => ['required', 'string', 'max:1000'],
    'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:5120'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $data = $this->validate();

    $settings = OrganizationSetting::current();

    unset($data['logo']);

    if ($this->logo) {
        if ($settings->logo_path) {
            Storage::disk(OrganizationSetting::logoDisk())->delete($settings->logo_path);
        }

        $webp = ImageCompressor::compressToWebp($this->logo);
        $path = 'org-logos/'.Str::uuid().'.webp';

        Storage::disk(OrganizationSetting::logoDisk())->put($path, $webp, 'public');

        $data['logo_path'] = $path;
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
        Storage::disk(OrganizationSetting::logoDisk())->delete($settings->logo_path);
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
                {{ __('Accepted formats: JPG, PNG, GIF, BMP, WebP (no HEIC/HEIF).') }}
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
                    <input type="file" wire:model="logo" accept=".jpg,.jpeg,.png,.gif,.bmp,.webp"
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

    @if($hospital)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Plan de suscripción') }}</flux:heading>
                <flux:subheading>
                    {{ __('Solo lectura — para cambiar de plan o reactivar tu cuenta, contacta al equipo de soporte.') }}
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <flux:text variant="subtle" size="sm">{{ __('Plan') }}</flux:text>
                    <div class="font-medium">
                        {{ config("billing.plans.{$hospital->plan}.name", ucfirst($hospital->plan)) }}
                    </div>
                </div>
                <div>
                    <flux:text variant="subtle" size="sm">{{ __('Estado') }}</flux:text>
                    <div>
                        <flux:badge size="sm" color="{{ $hospital->subscriptionAllowsAccess() ? 'green' : 'red' }}">
                            {{ $hospital->subscription_status->value }}
                        </flux:badge>
                    </div>
                </div>
                @if($hospital->subscription_status->value === 'trialing' && $hospital->trial_ends_at)
                    <div>
                        <flux:text variant="subtle" size="sm">{{ __('Trial termina') }}</flux:text>
                        <div class="font-medium">{{ $hospital->trial_ends_at->format('Y-m-d') }}</div>
                    </div>
                @endif
            </div>

            @if(!empty($hospital->features))
                <div>
                    <flux:text variant="subtle" size="sm">{{ __('Funciones incluidas') }}</flux:text>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach($hospital->features as $feature)
                            <flux:badge size="sm">{{ $feature }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
