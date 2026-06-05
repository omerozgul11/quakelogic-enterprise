<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'subject_type', 'subject_id', 'type', 'title', 'description', 'occurred_at'];
    protected function casts(): array { return ['occurred_at' => 'datetime']; }
    public function subject(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
