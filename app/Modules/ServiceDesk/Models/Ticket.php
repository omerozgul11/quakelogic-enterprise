<?php

namespace App\Modules\ServiceDesk\Models;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Inventory\Models\Product;
use App\Modules\ServiceDesk\Database\Factories\TicketFactory;
use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Enums\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'tickets';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'assigned_to',
        'company_id', 'contact_id', 'asset_id', 'inventory_product_id',
        'number', 'subject', 'description', 'type', 'status', 'priority', 'channel',
        'serial_number', 'rma_disposition',
        'due_at', 'opened_at', 'first_responded_at', 'resolved_at', 'closed_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'due_at' => 'datetime',
            'opened_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TicketStatus::Resolved->value, TicketStatus::Closed->value, TicketStatus::Cancelled->value,
        ]);
    }

    /** Past its SLA due date and still open. */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast() && $this->status->isOpen();
    }
}
