<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Company extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'name', 'company_type',
        'industry', 'cage_code', 'uei', 'duns', 'website', 'phone', 'email',
        'address_line1', 'city', 'state', 'zip', 'country',
        'annual_revenue', 'employee_count', 'notes', 'last_contact_date', 'next_follow_up_date',
    ];

    protected function casts(): array
    {
        return [
            'annual_revenue' => 'decimal:2',
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

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'cage_code' => $this->cage_code,
        ];
    }
}
