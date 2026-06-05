<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceMatrixItem extends Model
{
    protected $fillable = [
        'compliance_matrix_id', 'requirement_reference', 'requirement',
        'is_compliant', 'compliance_approach', 'owner', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_compliant' => 'boolean'];
    }

    public function matrix(): BelongsTo { return $this->belongsTo(ComplianceMatrix::class, 'compliance_matrix_id'); }
}
