<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $fillable = ['contact_id', 'chats', 'timestamp', 'replied', 'stage', 'program', 'city', 'information'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
