<x-filament::widget>
    <x-filament::card>
        <div x-data="{
            interval: null,
            fetchQr() {
                @this.call('refreshQrManually')
            },
            startPolling() {
                this.interval = setInterval(() => this.fetchQr(), 5000);
            }
        }" x-init="startPolling()" class="p-4">
            <div class="text-left">
                @if (isset($record->is_connected) && $record->is_connected)
                    <p class="text-green-600">Device sudah terhubung.</p>
                @elseif ($qr)
                    <h3 class="text-lg font-bold mb-2">QR Code</h3>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qr) }}"
                        alt="QR Code">
                    <p class="text-sm text-gray-600 mt-2">Scan QR ini di WhatsApp</p>
                @else
                    <h3 class="text-lg font-bold mb-2">QR Code</h3>
                    <p class="text-sm text-gray-500">Memuat QR Code..</p>
                @endif
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
