<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'remindable_type', 'remindable_id',
        'title', 'message', 'remind_at', 'sent_at', 'dismissed_at', 'channel',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function remindable()
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
