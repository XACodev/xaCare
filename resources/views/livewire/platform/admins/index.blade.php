<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed, layout};

layout('components.layouts.platform');

state([
    'invitation_note' => '',
    'generated_link' => null,
]);

mount(function () {
    abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);
});

$admins = computed(fn () => User::where('is_platform_admin', true)->orderBy('name')->get());

$invitations = computed(fn () => PlatformAdminInvitation::whereNull('accepted_at')
    ->where('expires_at', '>', now())
    ->orderByDesc('created_at')
    ->get());

$generateInvitation = function () {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    [$invitation, $plainTextToken] = PlatformAdminInvitation::generateFor(
        invitedBy: Auth::id(),
        note: $this->invitation_note ?: null,
    );

    $this->generated_link = route('platform.admin-invitations.accept', $plainTextToken);
    $this->invitation_note = '';
};

$revokeInvitation = function (int $invitationId) {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    PlatformAdminInvitation::where('id', $invitationId)->delete();
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <flux:heading size="xl">{{ __('Administradores de plataforma') }}</flux:heading>

    @if($generated_link)
        <flux:callout variant="success" icon="link" heading="{{ __('Invitación generada') }}">
            <p class="text-sm mb-2">{{ __('Copia este enlace ahora — no se volverá a mostrar.') }}</p>
            <div class="flex items-center gap-2">
                <flux:input readonly value="{{ $generated_link }}" wire:key="generated-link-input" />
                <flux:button size="sm" x-on:click="navigator.clipboard.writeText('{{ $generated_link }}')">
                    {{ __('Copiar') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <flux:heading size="lg">{{ __('Invitar administrador') }}</flux:heading>
        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
            <div class="flex-1 w-full">
                <flux:input wire:model="invitation_note" label="{{ __('Nota (opcional)') }}"
                    placeholder="{{ __('ej. Soporte L2') }}" />
            </div>
            <flux:button variant="primary" wire:click="generateInvitation" class="w-full sm:w-auto">
                {{ __('Generar invitación') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Invitaciones pendientes') }}</flux:heading>
        </div>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->invitations as $invitation)
                    <tr>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $invitation->note ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $invitation->expires_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="danger" wire:click="revokeInvitation({{ $invitation->id }})"
                                wire:confirm="{{ __('¿Revocar esta invitación?') }}">
                                {{ __('Revocar') }}
                            </flux:button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('Sin invitaciones pendientes.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Administradores actuales') }}</flux:heading>
        </div>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($this->admins as $admin)
                    <tr>
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $admin->name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500">{{ $admin->email }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
