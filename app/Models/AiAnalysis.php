<?php

namespace App\Models;

use App\Enums\AiJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiAnalysis extends Model
{
    use HasFactory;

    protected $table = 'ai_analyses';

    protected $fillable = [
        'organization_id', 'created_by', 'reviewed_by', 'subject_type', 'subject_id',
        'analysis_type', 'ai_provider', 'model_used', 'prompt_used', 'context_data',
        'output', 'status', 'human_decision', 'human_modified_output', 'reviewed_at',
        'input_tokens', 'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiJobStatus::class,
            'context_data' => 'array',
            'output' => 'array',
            'human_modified_output' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo { return $this->morphTo(); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
