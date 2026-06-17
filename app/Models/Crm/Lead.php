<?php

namespace App\Models\Crm;

use App\Enums\LeadStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_leads';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'company_id', 'contact_id',
        'title', 'contact_name', 'email', 'phone', 'source', 'status',
        'estimated_value', 'probability', 'expected_close_date', 'notes', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'estimated_value' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [LeadStatus::Won->value, LeadStatus::Lost->value]);
    }
}
