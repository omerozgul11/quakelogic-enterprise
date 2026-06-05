<?php

namespace App\Models;

use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FollowUp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'created_by', 'assigned_to', 'proposal_submission_id',
        'opportunity_id', 'contact_id', 'status', 'type', 'subject', 'message',
        'scheduled_date', 'sent_at', 'responded_at', 'is_automated',
    ];

    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'scheduled_date' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'is_automated' => 'boolean',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
}
