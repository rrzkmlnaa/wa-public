<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    protected $fillable = ['name'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(MessageLog::class);
    }
}
