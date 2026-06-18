<?php

namespace App\Modules\Finance\Models;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Crm\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Finance\Database\Factories\CreditNoteFactory;
use App\Modules\Finance\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CreditNote extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'finance_credit_notes';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'company_id', 'crm_invoice_id',
        'number', 'amount', 'currency', 'reason', 'status', 'issued_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'amount' => 'decimal:2',
            'issued_at' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): CreditNoteFactory
    {
        return CreditNoteFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'crm_invoice_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
