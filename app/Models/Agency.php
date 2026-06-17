<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Agency extends Model
{
    use HasFactory, Searchable, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'name', 'acronym',
        'agency_type', 'federal_code', 'website', 'phone', 'email',
        'address_line1', 'city', 'state', 'zip', 'country', 'notes',
        'last_contact_date', 'next_follow_up_date',
    ];

    protected function casts(): array
    {
        return [
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProposalSubmission::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'acronym' => $this->acronym,
            'federal_code' => $this->federal_code,
        ];
    }
}
