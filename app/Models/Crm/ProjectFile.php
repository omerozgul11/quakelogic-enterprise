<?php

namespace App\Models\Crm;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProjectFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_project_files';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'crm_project_folder_id', 'uploaded_by',
        'display_name', 'original_filename', 'stored_filename',
        'disk', 'path', 'mime_type', 'size', 'checksum', 'source',
        'version', 'is_current_version', 'parent_file_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'is_current_version' => 'boolean',
        ];
    }

    /** Root id of this file's version family (itself when it is the original). */
    public function rootId(): int
    {
        return $this->parent_file_id ?? $this->id;
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'crm_project_folder_id');
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
