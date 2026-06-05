<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentParsingJob extends Model
{
    protected $fillable = [
        'organization_id', 'document_type', 'document_id',
        'status', 'file_path', 'file_name', 'mime_type', 'file_size',
        'extraction_schema', 'started_at', 'completed_at', 'error_message',
    ];

    protected $casts = [
        'extraction_schema' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->morphTo();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function extractions()
    {
        return $this->hasMany(DocumentExtraction::class, 'document_parsing_job_id');
    }
}
