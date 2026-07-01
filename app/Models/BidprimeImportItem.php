<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidprimeImportItem extends Model
{
    protected $fillable = [
        'bidprime_import_id', 'bidprime_email_id', 'external_id', 'title',
        'action', 'opportunity_id', 'error_message', 'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(BidprimeImport::class, 'bidprime_import_id');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(BidprimeEmail::class, 'bidprime_email_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}
