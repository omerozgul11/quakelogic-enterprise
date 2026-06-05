<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'created_by', 'assigned_to', 'taskable_type', 'taskable_id',
        'title', 'description', 'status', 'priority', 'due_date', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function taskable(): MorphTo { return $this->morphTo(); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
