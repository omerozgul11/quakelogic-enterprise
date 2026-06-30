<?php

namespace App\Models\Crm;

use App\Enums\Crm\ExecutionRecordStatus;
use App\Enums\Crm\ExecutionRecordType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A field-execution event on a project — installation, commissioning, training,
 * warranty, inspection or service. One unified, typed record rather than four
 * near-identical tables.
 */
class ProjectExecutionRecord extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_project_execution_records';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'crm_project_site_id', 'performed_by', 'created_by',
        'type', 'title', 'status', 'scheduled_date', 'completed_date',
        'summary', 'outcome', 'customer_visible', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExecutionRecordType::class,
            'status' => ExecutionRecordStatus::class,
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'customer_visible' => 'boolean',
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class, 'crm_project_site_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
