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
        'ulid', 'organization_id', 'crm_project_id', 'uploaded_by',
        'display_name', 'original_filename', 'stored_filename',
        'disk', 'path', 'mime_type', 'size', 'checksum', 'source',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
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
