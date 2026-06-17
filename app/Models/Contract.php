<?php

namespace App\Models;

use App\Enums\ContractStage;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contract extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'proposal_submission_id', 'created_by',
        'contract_number', 'po_number', 'invoice_number',
        'stage', 'payment_status',
        'contract_value', 'amount_invoiced', 'amount_paid', 'currency',
        'signed_at', 'po_received_at', 'invoice_sent_at', 'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ContractStage::class,
            'payment_status' => PaymentStatus::class,
            'contract_value' => 'decimal:2',
            'amount_invoiced' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'signed_at' => 'date',
            'po_received_at' => 'date',
            'invoice_sent_at' => 'date',
            'paid_at' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function milestones(): HasMany { return $this->hasMany(DeliveryMilestone::class)->orderBy('sort_order')->orderBy('due_date'); }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
