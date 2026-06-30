<?php

namespace App\Models\Crm;

use App\Enums\Crm\SignoffType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A captured sign-off on a project — a signer's attestation with a timestamp
 * and an optional drawn signature image. Not Auditable (the base64 signature
 * would bloat the audit log); the project activity feed records the event.
 */
class ProjectSignoff extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_project_signoffs';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'crm_project_execution_record_id', 'captured_by',
        'type', 'signer_name', 'signer_title', 'signer_email', 'statement', 'signature_data', 'signed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => SignoffType::class,
            'signed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'crm_project_id');
    }

    public function executionRecord(): BelongsTo
    {
        return $this->belongsTo(ProjectExecutionRecord::class, 'crm_project_execution_record_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
