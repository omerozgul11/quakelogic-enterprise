<?php

namespace App\Models\Library;

use App\Models\Organization;
use App\Models\User;
use App\Support\Library\LinkTargets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An attachment of a library document to another record (proposal, PO, project,
 * opportunity, company, contact, invoice or supplier). `linkable_type` is the
 * Library's own short key resolved via LinkTargets — not Eloquent's morph map.
 */
class LibraryDocumentLink extends Model
{
    protected $fillable = [
        'ulid', 'organization_id', 'library_document_id',
        'linkable_type', 'linkable_id', 'note', 'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    /** Resolve the linked record (org-scoped), or null if it no longer exists. */
    public function resolveTarget(): ?Model
    {
        return LinkTargets::resolve($this->linkable_type, (int) $this->linkable_id, (int) $this->organization_id);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LibraryDocument::class, 'library_document_id');
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
