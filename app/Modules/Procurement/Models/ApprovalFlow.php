<?php

namespace App\Modules\Procurement\Models;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A configurable, amount-tiered approval chain for a procurement document type.
 * The matching flow is chosen at submit time by the document's total.
 */
class ApprovalFlow extends Model
{
    use SoftDeletes;

    protected $table = 'procurement_approval_flows';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by',
        'name', 'document_type', 'min_amount', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => ApprovalDocumentType::class,
            'min_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalFlowStep::class, 'procurement_approval_flow_id')->orderBy('position')->orderBy('id');
    }
}
