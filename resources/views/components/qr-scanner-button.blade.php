{{-- resources/views/components/qr-scanner-button.blade.php --}}
<div x-data="{
    open: false,
    scanner: null,
    result: null,
    start() {
        this.open = true;
        this.result = null;
        this.$nextTick(() => {
            this.scanner = window.xacareScanQr('qr-scanner-video', 'qr-scanner-canvas', (text) => {
                this.handleDecoded(text);
            });
        });
    },
    stop() {
        if (this.scanner) this.scanner.stop();
        this.open = false;
    },
    handleDecoded(text) {
        this.stop();
        try {
            const url = new URL(text);
            if (url.origin === window.location.origin) {
                window.location.href = text;
                return;
            }
        } catch (e) {}
        this.result = 'not-recognized';
    },
}">
    <flux:button type="button" data-qr-scanner-trigger @click="start" icon="qr-code">{{ __('Escanear') }}</flux:button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center">
        <div class="bg-white dark:bg-zinc-900 rounded-lg p-4 space-y-3 max-w-sm w-full">
            <video id="qr-scanner-video" class="w-full rounded" muted playsinline></video>
            <canvas id="qr-scanner-canvas" class="hidden"></canvas>
            <template x-if="result === 'not-recognized'">
                <div class="text-sm text-red-600">{{ __('QR no reconocido por xaCare') }}</div>
            </template>
            <flux:button type="button" variant="ghost" @click="stop">{{ __('Cerrar') }}</flux:button>
        </div>
    </div>
</div>
