<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentEmbedding extends Model
{
    protected $fillable = [
        'organization_id', 'source_type', 'source_id', 'source_label',
        'chunk_index', 'chunk_text', 'embedding', 'model',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'chunk_index' => 'integer',
        ];
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
