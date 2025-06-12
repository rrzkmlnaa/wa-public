<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $clientId;

    public function __construct(string $clientId)
    {
        $this->clientId = $clientId;
    }

    public function handle(): void
    {
        $device = Device::where('client_id', $this->clientId)->first();

        if (!$device) {
            Log::error("Device not found: {$this->clientId}");
            return;
        }

        try {
            $response = Http::timeout(60)->get("http://127.0.0.1:3001/device/{$this->clientId}/contacts");

            if (!$response->successful()) {
                Log::error("Failed to fetch contacts for {$this->clientId}: " . $response->body());
                return;
            }

            $contacts = $response->json();

            $now = now();
            $bulkData = [];

            foreach ($contacts as $contact) {
                // Cek apakah kontak sudah ada sebelumnya untuk device_id dan number yang sama
                $existingContact = Contact::where('device_id', $device->id)
                    ->where('number', $contact['number'])
                    ->first();

                // Jika tidak ada, tambah ke array bulkData
                if (!$existingContact) {
                    $bulkData[] = [
                        'device_id' => $device->id,
                        'name' => $contact['name'] ?? null,
                        'number' => $contact['number'],
                        'is_group' => $contact['is_group'] ?? false,
                        'synced_at' => $now,
                    ];
                } else {
                    // Jika ada, update saja data yang ada
                    $existingContact->update([
                        'name' => $contact['name'] ?? null,
                        'is_group' => $contact['is_group'] ?? false,
                        'synced_at' => $now,
                    ]);
                }
            }

            // Jika ada data baru yang tidak ada sebelumnya, lakukan insert secara massal
            if (count($bulkData) > 0) {
                Contact::insert($bulkData);
            }

            Log::info("Contacts synced for {$this->clientId}, total: " . count($contacts));
        } catch (\Throwable $e) {
            Log::error("Sync job failed for {$this->clientId}: " . $e->getMessage());
        }
    }
}
