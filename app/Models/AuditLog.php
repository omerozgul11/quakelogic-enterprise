<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'tags',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'tags' => 'array'];
    }

    public function auditable(): MorphTo { return $this->morphTo(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function record(string $event, $model, array $oldValues = [], array $newValues = []): static
    {
        return static::create([
            'organization_id' => auth()->user()?->organization_id,
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
