<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalTeamMember extends Model
{
    protected $fillable = ['proposal_submission_id', 'user_id', 'role', 'assigned_by'];

    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
