<?php

namespace App\Modules\AssetManagement\Models;

use App\Models\User;
use App\Modules\AssetManagement\Enums\MaintenanceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MaintenanceRecord extends Model
{
    use SoftDeletes;

    protected $table = 'asset_maintenance_records';

    protected $fillable = [
        'ulid', 'organization_id', 'asset_id', 'performed_by',
        'type', 'status', 'description', 'cost', 'performed_at', 'next_due_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'cost' => 'decimal:2',
            'performed_at' => 'date',
            'next_due_at' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
