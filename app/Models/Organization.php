<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'name', 'slug', 'legal_name', 'cage_code', 'duns', 'uei', 'ein',
        'website', 'phone', 'email', 'address_line1', 'address_line2', 'city',
        'state', 'zip', 'country', 'timezone', 'logo_path', 'is_active', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->ulid ??= (string) Str::ulid();
            $model->slug ??= Str::slug($model->name);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProposalSubmission::class);
    }

    public function agencies(): HasMany
    {
        return $this->hasMany(Agency::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
