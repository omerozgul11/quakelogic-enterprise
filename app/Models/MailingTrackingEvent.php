<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailingTrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_mailing_id', 'code', 'description', 'location', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function proposalMailing(): BelongsTo
    {
        return $this->belongsTo(ProposalMailing::class);
    }
}
