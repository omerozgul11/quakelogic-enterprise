<?php

namespace App\Models;

use App\Enums\CaptureStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CapturePlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'opportunity_id', 'capture_manager_id', 'created_by',
        'stage', 'probability_of_win', 'estimated_value', 'estimated_margin',
        'strategy', 'win_themes', 'discriminators', 'risks_summary',
        'is_incumbent', 'incumbent_name', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'stage' => CaptureStage::class,
            'probability_of_win' => 'decimal:2',
            'estimated_value' => 'decimal:2',
            'estimated_margin' => 'decimal:2',
            'is_incumbent' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function captureManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capture_manager_id');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(CaptureStageHistory::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(CaptureRisk::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CaptureTask::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(CaptureDecision::class);
    }
}
