<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityAssignment extends Model
{
    protected $fillable = ['opportunity_id', 'user_id', 'role', 'assigned_by'];
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
