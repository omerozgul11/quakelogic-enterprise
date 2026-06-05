<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentExtraction extends Model
{
    protected $fillable = [
        'document_parsing_job_id', 'field_name', 'extracted_value',
        'ai_output', 'human_corrected_output', 'confidence_score',
        'is_human_reviewed', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'ai_output' => 'array',
        'human_corrected_output' => 'array',
        'confidence_score' => 'float',
        'is_human_reviewed' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function parsingJob(): BelongsTo
    {
        return $this->belongsTo(DocumentParsingJob::class, 'document_parsing_job_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
