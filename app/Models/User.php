<?php

namespace App\Models;

use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'ulid', 'organization_id', 'name', 'email', 'title', 'phone', 'department',
        'avatar_path', 'password', 'is_active', 'hire_date',
        'commission_rate_override', 'timezone', 'notification_preferences',
        'pipeline_keywords', 'market_keywords',
        'product_expertise', 'industry_expertise', 'geographic_focus',
        'min_opportunity_value', 'max_opportunity_value', 'workload_score', 'workload_updated_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'hire_date' => 'date',
            'notification_preferences' => 'array',
            'pipeline_keywords' => 'array',
            'market_keywords' => 'array',
            'product_expertise' => 'array',
            'industry_expertise' => 'array',
            'geographic_focus' => 'array',
            'min_opportunity_value' => 'decimal:2',
            'max_opportunity_value' => 'decimal:2',
            'workload_score' => 'integer',
            'workload_updated_at' => 'datetime',
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

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'assigned_to');
    }

    /** Opportunities this user owns (claimed / locked). */
    public function ownedOpportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'owner_id');
    }

    public function opportunityStates(): HasMany
    {
        return $this->hasMany(OpportunityUserState::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProposalSubmission::class, 'owner_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function emailAccount(): HasOne
    {
        return $this->hasOne(EmailAccount::class);
    }

    /**
     * Where notification emails are delivered. App alerts go to the user's
     * connected work email (falling back to their login email); password-reset
     * and email-verification links always go to the login email they entered.
     */
    public function routeNotificationForMail(mixed $notification): string
    {
        if ($notification instanceof \Illuminate\Auth\Notifications\ResetPassword
            || $notification instanceof \Illuminate\Auth\Notifications\VerifyEmail) {
            return $this->email;
        }

        return $this->emailAccount?->email ?: $this->email;
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class, 'assigned_to');
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/'.$this->avatar_path) : null;
    }
}
