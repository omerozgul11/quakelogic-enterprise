<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MailingDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'proposal_mailing_id', 'uploaded_by', 'display_name',
        'original_filename', 'stored_filename', 'disk', 'path', 'mime_type',
        'size', 'document_type',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->ulid ??= (string) Str::ulid());
    }

    public function mailing(): BelongsTo
    {
        return $this->belongsTo(ProposalMailing::class, 'proposal_mailing_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getSizeFormattedAttribute(): string
    {
        $size = (int) $this->size;
        if ($size < 1024) {
            return "{$size} B";
        }
        if ($size < 1048576) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / 1048576, 1).' MB';
    }
}
