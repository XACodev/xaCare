<div class="relative mb-6 w-full">
    <x-mobile-back
        :href="auth()->user()?->hasRole('admin') && ! auth()->user()?->is_platform_admin ? route('settings.index') : route('dashboard')"
        :label="auth()->user()?->hasRole('admin') && ! auth()->user()?->is_platform_admin ? __('Configurations') : __('Dashboard')"
    />
    <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
    <flux:subheading size="lg" class="mb-6">{{ __('Manage your profile and account settings') }}</flux:subheading>
    <flux:separator variant="subtle" />
</div>
