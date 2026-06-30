<?php

namespace App\Models;

use App\Enums\RateQuoteStatus;
use App\Enums\ShipmentServiceLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ShipmentRateQuote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'proposal_mailing_id', 'carrier', 'service_line', 'status',
        'reference', 'contact_email',
        'origin_city', 'origin_state', 'origin_postal', 'origin_country',
        'dest_city', 'dest_state', 'dest_postal', 'dest_country',
        'ready_date', 'service_level',
        'weight', 'weight_unit', 'length', 'width', 'height', 'dim_unit',
        'freight_class', 'pallet_count', 'piece_count', 'accessorials',
        'amount', 'currency', 'transit_days', 'estimated_delivery', 'quote_reference',
        'quoted_at', 'requested_at', 'expires_at', 'source', 'notes', 'raw_response', 'created_by',
        'document_path', 'document_name', 'document_mime', 'document_size', 'document_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RateQuoteStatus::class,
            'service_line' => ShipmentServiceLine::class,
            'ready_date' => 'date',
            'estimated_delivery' => 'date',
            'quoted_at' => 'datetime',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'document_uploaded_at' => 'datetime',
            'document_size' => 'integer',
            'accessorials' => 'array',
            'raw_response' => 'array',
            'amount' => 'decimal:2',
            'weight' => 'decimal:2',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'pallet_count' => 'integer',
            'piece_count' => 'integer',
            'transit_days' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function proposalMailing(): BelongsTo
    {
        return $this->belongsTo(ProposalMailing::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Multi-tenant scope — every Shipments query must be org-scoped. */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** A returned price that's past its validity date is no longer dependable. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Whether a rate-sheet PDF is attached to this quote. */
    public function hasDocument(): bool
    {
        return $this->document_path !== null && $this->document_path !== '';
    }

    /** Human-readable attachment size, e.g. "1.2 MB". */
    public function documentSizeLabel(): ?string
    {
        $size = (int) $this->document_size;
        if (! $this->hasDocument() || $size <= 0) {
            return null;
        }
        if ($size < 1024) {
            return "{$size} B";
        }
        if ($size < 1048576) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / 1048576, 1).' MB';
    }

    /** "City, ST 12345" — blank parts are dropped. */
    public function originLabel(): string
    {
        return self::placeLabel($this->origin_city, $this->origin_state, $this->origin_postal, $this->origin_country);
    }

    public function destinationLabel(): string
    {
        return self::placeLabel($this->dest_city, $this->dest_state, $this->dest_postal, $this->dest_country);
    }

    private static function placeLabel(?string $city, ?string $state, ?string $postal, ?string $country): string
    {
        $line = trim(implode(', ', array_filter([
            $city,
            trim(($state ?? '').' '.($postal ?? '')),
        ], fn ($p) => trim((string) $p) !== '')));

        if ($country && strtoupper($country) !== 'US') {
            $line = trim($line.' '.strtoupper($country));
        }

        return $line;
    }
}
