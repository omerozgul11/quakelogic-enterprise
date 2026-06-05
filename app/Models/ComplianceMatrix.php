<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplianceMatrix extends Model
{
    use HasFactory;

    protected $fillable = ['proposal_submission_id', 'created_by', 'title', 'status', 'is_ai_generated'];

    protected function casts(): array
    {
        return ['is_ai_generated' => 'boolean'];
    }

    public function proposal(): BelongsTo { return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(ComplianceMatrixItem::class)->orderBy('sort_order'); }
}
