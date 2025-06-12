<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Device extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'client_id', 'is_connected'];

    protected static function booted()
    {
        static::created(function ($device) {
            try {
                Http::post('http://localhost:3001/device/start', [
                    'client_id' => $device->client_id,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed to initialize WhatsApp client: " . $e->getMessage());
            }
        });

        static::deleting(function (Device $device) {
            try {
                Http::delete("http://127.0.0.1:3001/device/{$device->client_id}/delete");
            } catch (\Throwable $e) {
                Log::error("Failed to delete WhatsApp session: " . $e->getMessage());
            }
        });
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
}
