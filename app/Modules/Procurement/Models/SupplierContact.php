<?php

namespace App\Modules\Procurement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupplierContact extends Model
{
    use SoftDeletes;

    protected $table = 'procurement_supplier_contacts';

    protected $fillable = [
        'ulid', 'organization_id', 'procurement_supplier_id',
        'name', 'title', 'email', 'phone', 'is_primary',
        'portal_enabled', 'portal_last_login_at',
    ];

    // portal_password is set explicitly (hashed) — never mass-assigned or serialized.
    protected $hidden = ['portal_password'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'portal_enabled' => 'boolean',
            'portal_last_login_at' => 'datetime',
        ];
    }

    /** Whether this contact can sign in to the vendor portal right now. */
    public function canUsePortal(): bool
    {
        if (! $this->portal_enabled || empty($this->portal_password) || ! $this->email || ! $this->supplier) {
            return false;
        }

        // status is cast to the SupplierStatus enum; compare on its value.
        $status = $this->supplier->status;

        return (is_object($status) ? $status->value : $status) === 'active';
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'procurement_supplier_id');
    }
}
