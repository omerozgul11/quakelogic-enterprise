<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SamImportItem extends Model
{
    protected $fillable = [
        'sam_import_id', 'external_id', 'solicitation_number',
        'title', 'action', 'opportunity_id', 'error_message', 'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(SamImport::class, 'sam_import_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
