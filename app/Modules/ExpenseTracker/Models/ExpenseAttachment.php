<?php

namespace App\Modules\ExpenseTracker\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExpenseAttachment extends Model
{
    protected $table = 'expense_attachments';

    protected $fillable = [
        'ulid', 'organization_id', 'expense_id', 'uploaded_by',
        'display_name', 'original_filename', 'stored_filename',
        'disk', 'path', 'mime_type', 'size', 'checksum',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
