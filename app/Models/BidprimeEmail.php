<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A BidPrime alert email read from the Gmail inbox. Holds the raw message for
 * audit + re-parsing and links to the opportunities extracted from it.
 *
 * Intentionally NOT Auditable — the raw HTML/text would bloat the audit log.
 */
class BidprimeEmail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'bidprime_import_id',
        'gmail_message_id', 'gmail_uid', 'thread_id', 'from_email', 'from_name', 'subject', 'received_at',
        'raw_html', 'raw_text', 'status', 'opportunities_found', 'parse_error', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'opportunities_found' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(BidprimeImport::class, 'bidprime_import_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BidprimeImportItem::class, 'bidprime_email_id');
    }
}
