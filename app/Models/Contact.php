<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Contact extends Model
{
    use HasFactory, Searchable, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'agency_id', 'company_id',
        'first_name', 'last_name', 'title', 'department', 'email', 'phone', 'mobile',
        'linkedin_url', 'is_decision_maker', 'is_key_contact', 'notes',
        'last_contact_date', 'next_follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'is_decision_maker' => 'boolean',
            'is_key_contact' => 'boolean',
            'last_contact_date' => 'date',
            'next_follow_up_date' => 'date',
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

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'title' => $this->title,
            'agency_name' => $this->agency?->name,
            'company_name' => $this->company?->name,
        ];
    }
}
