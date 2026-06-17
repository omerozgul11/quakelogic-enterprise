<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxPin extends Model
{
    protected $fillable = ['user_id', 'organization_id', 'thread_key'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
