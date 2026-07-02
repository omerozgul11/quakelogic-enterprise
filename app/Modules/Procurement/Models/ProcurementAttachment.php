<?php

namespace App\Modules\Procurement\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A file attached to a procurement document (PR / Quotation / PO / Bill).
 * Stored on the private `local` disk; served only through an authorized
 * controller action.
 */
class ProcurementAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'attachable_type', 'attachable_id',
        'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by',
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

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
