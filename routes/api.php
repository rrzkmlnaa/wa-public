<?php
// routes/api.php

use App\Jobs\SyncContactsJob;
use App\Models\Contact;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::post('/send-whatsapp', function (\Illuminate\Http\Request $request) {
  $number = $request->number;
  $message = $request->message;

  Http::post('http://localhost:3001/send-message', [
    'number' => $number,
    'message' => $message
  ]);

  return response()->json(['status' => 'sent']);
});

Route::post('/device-connected', function (\Illuminate\Http\Request $request) {
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
});

Route::post('/sync-contacts/{clientId}', function ($clientId) {
  SyncContactsJob::dispatch($clientId);
  return response()->json(['message' => 'Sync job dispatched']);
});

Route::post('/message-log', function (Request $request) {
  try {
    $validated = $request->validate([
      'client_id' => 'required|string',
      'number' => 'required|string|max:20',
      'chats' => 'required|array',
      'timestamp' => 'required',
      'replied' => 'required',
    ]);

    $device = Device::where('client_id', $validated['client_id'])->first();
    if (!$device) {
      return response()->json(['message' => 'Device not found'], 404);
    }

    $contact = Contact::where('device_id', $device->id)
      ->where('number', $validated['number'])
      ->first();
    if (!$contact) {
      return response()->json(['message' => 'Contact not found'], 404);
    }

    // Cek apakah sudah ada log untuk kontak ini
    $existingLog = $contact->messageLogs()->first();

    if ($existingLog) {
      // Ambil chats lama
      // $oldChats = json_decode($existingLog->chats, true) ?? [];

      // // Gabungkan dengan chats baru
      // $mergedChats = array_merge($oldChats, [$validated['chats']]);

      // Simpan update
      $existingLog->update([
        'chats' => json_encode($validated['chats']),
        'timestamp' => Carbon::createFromTimestamp($validated['timestamp'], 'Asia/Jakarta')->toDateTimeString(),
        'replied' => $validated['replied']
      ]);
    } else {
      // Buat log baru
      $contact->messageLogs()->create([
        'chats' => json_encode($validated['chats']), // simpan dalam array
        'timestamp' => Carbon::createFromTimestamp($validated['timestamp'], 'Asia/Jakarta')->toDateTimeString(),
        'replied' => $validated['replied']
      ]);
    }

    return response()->json(['message' => 'Log saved or updated']);
  } catch (\Illuminate\Validation\ValidationException $e) {
    Log::error('Error:', $e->getMessage());
    return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
  } catch (\Exception $e) {
    Log::error('Failed to save message log: ' . $e->getMessage());
    return response()->json(['message' => 'Failed to save log', 'error' => $e->getMessage()], 500);
  }
});
