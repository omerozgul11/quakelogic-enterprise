<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProposalFile extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'proposal_submission_id', 'uploaded_by', 'approved_by',
        'display_name', 'original_filename', 'stored_filename', 'disk', 'path',
        'mime_type', 'size', 'checksum', 'document_type', 'status',
        'version', 'is_current_version', 'parent_file_id', 'notes', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'is_current_version' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= (string) Str::ulid());
    }

    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function getFileSizeFormattedAttribute(): string
    {
        $size = $this->size;
        if ($size < 1024) return "{$size} B";
        if ($size < 1048576) return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }
}
