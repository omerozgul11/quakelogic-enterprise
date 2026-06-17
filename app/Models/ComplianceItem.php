<?php

namespace App\Models;

use App\Enums\ComplianceStatus;
use App\Enums\ComplianceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceItem extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'organization_id', 'created_by', 'type', 'name', 'identifier',
        'status', 'issuer', 'issued_at', 'expires_at', 'renewal_interval', 'reference_url', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ComplianceType::class,
            'status' => ComplianceStatus::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /** Days until expiry (negative = already expired); null when no expiry tracked. */
    public function daysUntilExpiry(): ?int
    {
        return $this->expires_at ? (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false) : null;
    }
}
