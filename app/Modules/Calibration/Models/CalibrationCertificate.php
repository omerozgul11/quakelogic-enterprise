<?php

namespace App\Modules\Calibration\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Calibration\Database\Factories\CalibrationCertificateFactory;
use App\Modules\Calibration\Enums\CalibrationResult;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CalibrationCertificate extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'calibration_certificates';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'asset_id', 'inventory_product_id', 'performed_by',
        'certificate_number', 'result', 'nist_traceable', 'method', 'standard_used', 'technician', 'serial_number',
        'calibrated_at', 'due_at', 'interval_months', 'measurements', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'result' => CalibrationResult::class,
            'nist_traceable' => 'boolean',
            'calibrated_at' => 'date',
            'due_at' => 'date',
            'interval_months' => 'integer',
            'measurements' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): CalibrationCertificateFactory
    {
        return CalibrationCertificateFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }
}
