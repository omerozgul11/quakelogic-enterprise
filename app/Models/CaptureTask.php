<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureTask extends Model
{
    protected $fillable = ['capture_plan_id', 'assigned_to', 'created_by', 'title', 'description', 'status', 'due_date', 'completed_at'];
    protected function casts(): array { return ['due_date' => 'date', 'completed_at' => 'datetime']; }
    public function capturePlan(): BelongsTo { return $this->belongsTo(CapturePlan::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
