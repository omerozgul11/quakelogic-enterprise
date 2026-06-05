<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityPartner extends Model
{
    protected $fillable = ['opportunity_id', 'company_id', 'company_name', 'role', 'notes'];
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
