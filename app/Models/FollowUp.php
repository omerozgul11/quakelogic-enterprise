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
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'organization_id', 'created_by', 'assigned_to', 'proposal_submission_id',
        'opportunity_id', 'contact_id', 'status', 'type', 'subject', 'message',
        'scheduled_date', 'sent_at', 'responded_at', 'read_at', 'is_automated',
    ];

    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'scheduled_date' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'read_at' => 'datetime',
            'is_automated' => 'boolean',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }

    /**
     * Is this message unread for the given viewer? A message counts as unread
     * when it hasn't been read yet AND it isn't something the viewer personally
     * hand-wrote — so coworker messages, automated digests and reminders show as
     * unread, but your own typed notes never do.
     */
    public function isUnreadFor(int $viewerId): bool
    {
        return $this->read_at === null
            && !($this->created_by === $viewerId && !$this->is_automated);
    }

    /**
     * Unread inbox messages visible to a viewer — mirrors the visibility rules of
     * the inbox (FollowUpController::index) so the nav badge count always matches
     * the dots shown in the list. Apply organization scoping at the call site.
     */
    public function scopeUnreadForViewer($query, User $user)
    {
        $userId = $user->id;
        $isAdmin = $user->hasRole('Super Admin');

        return $query
            ->whereNull('read_at')
            // exclude messages the viewer personally hand-wrote (created_by ==
            // viewer and not automated); keep automated + coworker messages.
            ->where(fn ($q) => $q
                ->whereNull('created_by')
                ->orWhere('created_by', '!=', $userId)
                ->orWhere('is_automated', true))
            ->where(fn ($v) => $v
                ->where(fn ($w) => $w
                    ->whereNotNull('proposal_submission_id')
                    ->whereHas('proposal', function ($p) use ($userId, $isAdmin) {
                        if (!$isAdmin) {
                            $p->where('owner_id', $userId)
                                ->orWhere('proposal_manager_id', $userId)
                                ->orWhere('created_by', $userId)
                                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $userId));
                        }
                    }))
                ->orWhere(fn ($w) => $w
                    ->whereNull('proposal_submission_id')
                    ->where(fn ($x) => $x->where('assigned_to', $userId)->orWhere('created_by', $userId))));
    }
}
