<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['proposal_submission_id', 'user_id', 'note_type', 'content', 'is_private'];

    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
    }

    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
