<?php

namespace App\Models;

use App\Enums\DeliveryRisk;
use App\Enums\MailingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProposalMailing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'proposal_submission_id', 'carrier',
        'ups_tracking_number', 'recipient_name', 'recipient_address', 'deadline',
        'status', 'scheduled_delivery', 'delivered_at', 'received_by', 'on_time',
        'proof_url', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MailingStatus::class,
            'deadline' => 'date',
            'scheduled_delivery' => 'date',
            'delivered_at' => 'datetime',
            'on_time' => 'boolean',
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

    public function proposalSubmission(): BelongsTo
    {
        return $this->belongsTo(ProposalSubmission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(MailingTrackingEvent::class)->latest('occurred_at');
    }

    /** Multi-tenant scope — every Shipments query must be org-scoped. */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** Still moving — the poller only refreshes these. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [MailingStatus::Delivered->value, MailingStatus::Returned->value]);
    }

    /**
     * On-time evaluation vs the deadline. Uses the cached `on_time` once
     * delivered; otherwise compares the scheduled ETA (or today, if past due)
     * against the deadline.
     */
    public function risk(): DeliveryRisk
    {
        if ($this->status === MailingStatus::Exception) {
            return DeliveryRisk::Exception;
        }

        if ($this->status === MailingStatus::Delivered) {
            if ($this->on_time === null || $this->deadline === null) {
                return DeliveryRisk::DeliveredOnTime;
            }
            return $this->on_time ? DeliveryRisk::DeliveredOnTime : DeliveryRisk::DeliveredLate;
        }

        if ($this->deadline === null) {
            return DeliveryRisk::Unknown;
        }

        $eta = $this->scheduled_delivery ?? Carbon::today();
        return $eta->gt($this->deadline) ? DeliveryRisk::AtRisk : DeliveryRisk::OnTrack;
    }
}
