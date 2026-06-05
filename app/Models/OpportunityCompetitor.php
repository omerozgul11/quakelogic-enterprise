<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityCompetitor extends Model
{
    protected $fillable = ['opportunity_id', 'company_name', 'strength', 'weakness', 'notes'];
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
}
