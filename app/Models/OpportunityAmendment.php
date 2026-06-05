<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityAmendment extends Model
{
    protected $fillable = ['opportunity_id', 'amendment_number', 'change_type', 'description', 'new_due_date', 'changed_fields', 'detected_at'];
    protected function casts(): array { return ['new_due_date' => 'date', 'changed_fields' => 'array', 'detected_at' => 'datetime']; }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
}
