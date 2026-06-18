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
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
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
