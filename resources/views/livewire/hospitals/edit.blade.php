<?php

use App\Models\Hospital;
use App\Models\HospitalInvitation;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'hospital' => null,
    'name' => '',
    'plan' => 'basic',
    'is_active' => true,
    'success_message' => null,
    'invitations' => [],
    'invitation_note' => '',
    'generated_link' => null,
]);

mount(function (string|int $hospital) {
    abort_unless(Auth::check() && Auth::user()->is_super_admin, 403);

    $h = Hospital::findOrFail($hospital);

    $this->hospital = $h;
    $this->name = $h->name;
    $this->plan = $h->plan;
    $this->is_active = $h->is_active;

    $this->loadInvitations();
});

rules([
    'name' => ['required', 'string', 'max:255'],
    'plan' => ['required', 'string', 'max:50'],
    'is_active' => ['boolean'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    $data = $this->validate();

    $this->hospital->update($data);

    $this->success_message = __('Hospital updated.');
};

$loadInvitations = function () {
    $this->invitations = HospitalInvitation::withoutGlobalScopes()
        ->where('hospital_id', $this->hospital->id)
        ->orderByDesc('created_at')
        ->get();
};

$generateInvitation = function () {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    [$invitation, $plainTextToken] = HospitalInvitation::generateFor(
        hospitalId: $this->hospital->id,
        invitedBy: Auth::id(),
        note: $this->invitation_note ?: null,
    );

    $this->generated_link = route('hospital-invitations.accept', $plainTextToken);
    $this->invitation_note = '';

    $this->loadInvitations();
};

$revokeInvitation = function (int $invitationId) {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    HospitalInvitation::withoutGlobalScopes()
        ->where('hospital_id', $this->hospital->id)
        ->where('id', $invitationId)
        ->delete();

    $this->loadInvitations();
};

?>

<div class="max-w-2xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Edit Hospital') }}</flux:heading>
            <flux:subheading>{{ __('Only Super Admin') }}</flux:subheading>
        </div>
        <flux:link href="{{ route('hospitals.index') }}" class="text-sm">{{ __('Back') }}</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input wire:model.live="name" label="{{ __('Name') }}" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Plan') }}</label>
        <select wire:model.live="plan"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
            <option value="basic">{{ __('Basic') }}</option>
            <option value="pro">{{ __('Pro') }}</option>
        </select>

        <flux:checkbox wire:model.live="is_active" label="{{ __('Active') }}" />

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Invitations') }}</flux:heading>
            <flux:subheading>{{ __('One-time links so a client can create their own admin account for this hospital.') }}</flux:subheading>
        </div>

        @if($generated_link)
            <flux:callout variant="success" icon="link" heading="{{ __('Invitation link generated') }}">
                <p class="text-sm mb-2">{{ __('Copy this link now — it will not be shown again.') }}</p>
                <div class="flex items-center gap-2">
                    <flux:input readonly value="{{ $generated_link }}" wire:key="generated-link-input" />
                    <flux:button size="sm" x-on:click="navigator.clipboard.writeText('{{ $generated_link }}')">
                        {{ __('Copy') }}
                    </flux:button>
                </div>
            </flux:callout>
        @endif

        <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
            <div class="flex-1 w-full">
                <flux:input wire:model="invitation_note" label="{{ __('Note (optional)') }}"
                    placeholder="{{ __('e.g. Dr. Juan Pérez, Hospital San Rafael') }}" />
            </div>
            <flux:button variant="primary" wire:click="generateInvitation" class="w-full sm:w-auto">
                {{ __('Generate invitation') }}
            </flux:button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Note') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Expires') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($invitations as $invitation)
                        <tr>
                            <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $invitation->note ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @php($status = $invitation->status())
                                <flux:badge size="sm" color="{{ $status === 'pending' ? 'green' : ($status === 'accepted' ? 'blue' : 'red') }}">
                                    {{ __(ucfirst($status)) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-500">{{ $invitation->expires_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($status === 'pending')
                                    <flux:button size="sm" variant="danger"
                                        wire:click="revokeInvitation({{ $invitation->id }})"
                                        wire:confirm="{{ __('Revoke this invitation?') }}">
                                        {{ __('Revoke') }}
                                    </flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('No invitations yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
