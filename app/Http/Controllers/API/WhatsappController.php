<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SyncContactsJob;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Program;
use App\Models\Stage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenSpout\Reader\Exception\ReaderException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Rap2hpoutre\FastExcel\FastExcel;

class WhatsappController extends Controller
{
    public function sendWhatsapp(Request $request)
    {
        $number = $request->number;
        $message = $request->message;

        Http::post('http://localhost:3001/send-message', [
            'number' => $number,
            'message' => $message
        ]);

        return response()->json(['status' => 'sent']);
    }

    public function deviceConnected(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
        ]);

        $device = Device::where('client_id', $validated['client_id'])->first();

        if (!$device) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $device->is_connected = true;
        $device->save();

        return response()->json(['status' => 'updated']);
    }

    public function syncContacts($clientId)
    {
        SyncContactsJob::dispatch($clientId);
        return response()->json(['message' => 'Sync job dispatched']);
    }

    public function messageLog(Request $request)
    {
        try {
            $validated = $request->validate([
                'client_id' => 'required|string',
                'number' => 'required|string|max:20',
                'chats' => 'required|array',
                'timestamp' => 'required',
                'replied' => 'required',
            ]);

            $device = Device::where('client_id', $validated['client_id'])->firstOrFail();
            $contact = Contact::where('device_id', $device->id)
                ->where('number', $validated['number'])
                ->firstOrFail();

            $contact->messageLogs()->updateOrCreate(
                ['contact_id' => $contact->id],
                [
                    'chats' => json_encode($validated['chats']),
                    'timestamp' => Carbon::createFromTimestamp($validated['timestamp'], 'Asia/Jakarta')->toDateTimeString(),
                    'replied' => $validated['replied']
                ]
            );

            return response()->json(['message' => 'Log saved or updated']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation Error:', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save message log: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to save log', 'error' => $e->getMessage()], 500);
        }
    }

    public function initPipeline(Request $request)
    {
        try {
            // Validasi file
            $request->validate([
                'doc' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $file = $request->file('doc');
            $path = $file->getRealPath();

            // Baca file dengan FastExcel
            $rows = (new FastExcel)->import($path);

            if ($rows->isEmpty()) {
                throw new \Exception('Data kosong di file Excel.');
            }

            $payloads = [];

            foreach ($rows as $row) {
                $keys = array_change_key_case($row, CASE_LOWER);
                $noWa = $keys['no whatsapp'] ?? null;
                $program = $keys['program'] ?? null;
                $weight = $keys['bobot'] ?? null;
                $city = $keys['kota'] ?? null;
                $information = $keys['keterangan'] ?? null;

                $stage = $this->determineStage($weight);

                if ($noWa && is_string($noWa)) {
                    $payloads[] = [
                        'number' => $noWa,
                        'program' => $program,
                        'stage' => $stage,
                        'city' => $city,
                        'information' => $information,
                    ];
                }
            }

            if (empty($payloads)) {
                throw new \Exception('Payloads tidak ditemukan atau kosong.');
            }

            // Format nomor telepon
            $numbersOnly = array_column($payloads, 'number');
            $formattedNumbers = formatNomorTelepon($numbersOnly);

            $final = [];
            $programSet = [];

            foreach ($formattedNumbers as $i => $number) {
                $data = [
                    'number' => $number,
                    'program' => $payloads[$i]['program'] ?? null,
                    'stage' => $payloads[$i]['stage'] ?? null,
                    'city' => $payloads[$i]['city'] ?? null,
                    'information' => $payloads[$i]['information'] ?? null
                ];

                $final[] = $data;

                $program = strtoupper(trim($payloads[$i]['program'] ?? ''));

                if ($program !== '') {
                    $programSet[$program] = true;  // pakai associative array untuk hilangkan duplikat case-insensitive
                }
            }

            foreach ($final as $data) {
                try {
                    // Cari contact by nomor
                    $contact = Contact::where('number', $data['number'])->first();

                    if (!$contact) {
                        Log::info("Contact tidak ditemukan untuk nomor: {$data['number']}");
                        continue;
                    }

                    // Cari stage_id berdasarkan stage name
                    $stage = Stage::where('name', $data['stage'])->first();

                    if (!$stage) {
                        Log::info("Stage tidak ditemukan untuk: {$data['stage']}");
                        continue;
                    }

                    // Update contact
                    $contact->update([
                        'program_id' => Program::firstOrCreate(
                            ['name' => strtoupper(trim($data['program']))],
                            ['name' => strtoupper(trim($data['program']))]
                        )->id,
                        'stage_id' => $stage->id,
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Gagal update contact untuk nomor: {$data['number']}", [
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            return response()->json([
                'total' => count($final),
                'preview' => 'Success'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validasi gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal init pipeline: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
        }
    }

    public function initPrograms(Request $request)
    {
        try {
            // Validasi file
            $request->validate([
                'doc' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $file = $request->file('doc');
            $path = $file->getRealPath();

            // Baca file dengan FastExcel
            $rows = (new FastExcel)->import($path);

            if ($rows->isEmpty()) {
                throw new \Exception('Data kosong di file Excel.');
            }

            $payloads = [];

            foreach ($rows as $row) {
                $keys = array_change_key_case($row, CASE_LOWER);
                $noWa = $keys['no whatsapp'] ?? null;
                $program = $keys['program'] ?? null;

                if ($noWa && is_string($noWa)) {
                    $payloads[] = [
                        'program' => $program,
                    ];
                }
            }

            if (empty($payloads)) {
                throw new \Exception('Payloads tidak ditemukan atau kosong.');
            }

            // Format nomor telepon
            $numbersOnly = array_column($payloads, 'number');
            $formattedNumbers = formatNomorTelepon($numbersOnly);
            $programSet = [];

            foreach ($formattedNumbers as $i => $number) {
                $data = [
                    'program' => $payloads[$i]['program'] ?? null,
                ];

                $final[] = $data;

                $program = strtoupper(trim($payloads[$i]['program'] ?? ''));

                if ($program !== '') {
                    $programSet[$program] = true;
                }
            }

            // Insert ke database (program baru)
            foreach (array_keys($programSet) as $programName) {
                Program::create(['name' => $programName]);
            }

            return response()->json([
                'total' => count($final),
                'preview' => 'Success'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validasi gagal: ' . $e->getMessage());
            return response()->json(['message' => 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Gagal init pipeline: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper untuk menentukan stage
     */
    private function determineStage($weight)
    {
        $weightValue = $this->convertPercentageToDecimal($weight);

        return match (true) {
            $weightValue >= 0 && $weightValue <= 20 => 'forecast',
            $weightValue >= 21 && $weightValue <= 50 => 'foreseen',
            $weightValue >= 51 && $weightValue <= 90 => 'firm',
            $weightValue >= 91 && $weightValue <= 100 => 'backlog',
            default => 'unknown',
        };
    }


    protected function convertPercentageToDecimal(?string $percentage): float
    {
        if (empty($percentage)) {
            return 0.00;
        }

        $percentage = trim(str_replace('%', '', $percentage));
        $percentage = str_replace(',', '.', $percentage);

        $floatValue = floatval($percentage);

        return $floatValue * 100;
    }
}
