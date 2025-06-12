<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeviceQrCodeWidget extends Widget
{
    protected static string $view = 'filament.widgets.device-qr-code-widget';

    public $record;
    public $qr = null;

    protected $listeners = ['refreshQr' => 'fetchQr'];

    public function mount(): void
    {
        $this->fetchQr();
    }

    public function fetchQr(): void
    {
        if ($this->record && !$this->record->is_connected) {
            try {
                $response = Http::get("http://127.0.0.1:3001/device/{$this->record->client_id}/qr");

                if ($response->successful() && $response->json('qr')) {
                    $this->qr = $response->json('qr');
                }
            } catch (\Throwable $e) {
                $this->qr = null;
                Log::error('Error fetchQr', $e->getMessage());
            }
        }
    }

    protected function getViewData(): array
    {
        return [
            'qr' => $this->qr,
            'record' => $this->record,
        ];
    }

    public function refreshQrManually()
    {
        $this->fetchQr();
    }

    protected static ?int $pollingInterval = 5000; // polling setiap 5 detik
}
