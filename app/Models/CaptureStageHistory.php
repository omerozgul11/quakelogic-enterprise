<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureStageHistory extends Model
{
    protected $fillable = ['capture_plan_id', 'changed_by', 'from_stage', 'to_stage', 'notes', 'changed_at'];
    protected function casts(): array { return ['changed_at' => 'datetime']; }
    public function capturePlan(): BelongsTo { return $this->belongsTo(CapturePlan::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
