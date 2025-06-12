<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Filament\Widgets\DeviceQrCodeWidget;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ViewDevice extends ViewRecord
{
  protected static string $resource = DeviceResource::class;

  public function mount($record): void
  {
    parent::mount($record);

    $device = $this->record;

    try {
      // Kirim permintaan untuk start device ke Node.js
      $response = Http::timeout(5)->post('http://127.0.0.1:3001/device/start', [
        'client_id' => $device->client_id,
      ]);

      if ($response->successful()) {
        Log::info("Started device {$device->client_id} from ViewDevice.");
        Notification::make()
          ->title('Devices Started')
          ->body($response->json('message'))
          ->success()
          ->send();
      } else {
        Log::warning("Failed to start device {$device->client_id}: " . $response->body());
      }
    } catch (\Throwable $e) {
      Log::error("Error starting device {$device->client_id}: " . $e->getMessage());
    }
  }

  public function getHeaderWidgets(): array
  {
    return [
      DeviceQrCodeWidget::make(['device' => $this->record]),
    ];
  }
}
