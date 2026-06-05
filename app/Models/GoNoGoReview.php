<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoNoGoReview extends Model
{
    protected $fillable = [
        'opportunity_id', 'reviewed_by', 'decision', 'rationale',
        'strategic_fit_score', 'win_probability', 'estimated_value', 'estimated_margin', 'criteria_scores',
    ];
    protected function casts(): array { return ['criteria_scores' => 'array', 'strategic_fit_score' => 'decimal:2', 'win_probability' => 'decimal:2', 'estimated_value' => 'decimal:2', 'estimated_margin' => 'decimal:2']; }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
