<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureDecision extends Model
{
    protected $fillable = ['capture_plan_id', 'decided_by', 'title', 'description', 'decision', 'rationale', 'decided_at'];
    protected function casts(): array { return ['decided_at' => 'datetime']; }
    public function capturePlan(): BelongsTo { return $this->belongsTo(CapturePlan::class); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
}
