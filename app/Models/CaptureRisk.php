<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptureRisk extends Model
{
    use HasFactory;
    protected $fillable = ['capture_plan_id', 'created_by', 'title', 'description', 'likelihood', 'impact', 'risk_score', 'mitigation_strategy', 'status'];
    public function capturePlan(): BelongsTo { return $this->belongsTo(CapturePlan::class); }
}
