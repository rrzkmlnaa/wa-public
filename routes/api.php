<?php

use App\Http\Controllers\API\WhatsappController;
use Illuminate\Support\Facades\Route;

Route::post('/send-whatsapp', [WhatsappController::class, 'sendWhatsapp']);
Route::post('/device-connected', [WhatsappController::class, 'deviceConnected']);
Route::post('/sync-contacts/{clientId}', [WhatsappController::class, 'syncContacts']);
Route::post('/message-log', [WhatsappController::class, 'messageLog']);
Route::post('/init-pipeline', [WhatsappController::class, 'initPipeline']);
Route::post('/init-program', [WhatsappController::class, 'initPrograms']);
