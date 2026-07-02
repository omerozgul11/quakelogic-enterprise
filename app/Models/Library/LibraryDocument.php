<?php

namespace App\Models\Library;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A file in the Document Library. Stored on the private `local` disk; served
 * only through the authorized LibraryController preview/download actions.
 * Supports version families (parent_document_id + is_current_version) and, when
 * shared + ai_indexed, is embedded into the org knowledge base for QuakeBot.
 */
class LibraryDocument extends Model
{
    use SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'library_folder_id', 'uploaded_by',
        'display_name', 'description', 'original_filename', 'stored_filename',
        'disk', 'path', 'mime_type', 'extension', 'size', 'checksum',
        'visibility', 'owner_id', 'ai_indexed',
        'version', 'is_current_version', 'parent_document_id',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
            'is_current_version' => 'boolean',
            'ai_indexed' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    /** Root id of this file's version family (itself when it is the original). */
    public function rootId(): int
    {
        return $this->parent_document_id ?? $this->id;
    }

    /** Human-readable file size (kept a method, not an $appends accessor, so partial selects stay strict-mode safe). */
    public function sizeLabel(): string
    {
        $bytes = (int) ($this->size ?? 0);
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));

        return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }

    /** Whether the browser can render this inline in an iframe (pdf/image/text). */
    public function isNativelyPreviewable(): bool
    {
        $mime = (string) $this->mime_type;

        return str_contains($mime, 'pdf')
            || str_starts_with($mime, 'image/')
            || str_starts_with($mime, 'text/');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** Documents the user may see: everything shared, plus their own private ones. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', 'shared')
                ->orWhere(fn (Builder $p) => $p->where('visibility', 'private')->where('owner_id', $user->id));
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->visibility !== 'private' || (int) $this->owner_id === (int) $user->id;
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(LibraryFolder::class, 'library_folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(LibraryDocumentLink::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }
}
