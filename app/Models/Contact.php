<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'device_id',
        'name',
        'number',
        'is_group',
        'synced_at',
        'program_id',
        'stage_id',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function getLatestMessageTimestampAttribute()
    {
        return optional($this->messageLogs()->latest('timestamp')->first())->timestamp;
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }
}
