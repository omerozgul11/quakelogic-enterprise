<?php

namespace App\Support\Library;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Invoice;
use App\Models\Crm\Project;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

/**
 * The registry of record types a library document can be attached to. Kept
 * deliberately self-contained (its own short "type" vocabulary + manual
 * resolution) so the Library's polymorphic links never touch Eloquent's global
 * morph map and can't affect the app's existing polymorphs. Every lookup is
 * org-scoped.
 */
class LinkTargets
{
    /**
     * @return array<string, array{label:string, model:class-string, columns:array<int,string>}>
     */
    public static function map(): array
    {
        return [
            'proposal' => [
                'label' => 'Proposal',
                'model' => ProposalSubmission::class,
                'columns' => ['proposal_number', 'project_name', 'solicitation_number'],
            ],
            'purchase_order' => [
                'label' => 'Purchase Order',
                'model' => PurchaseOrder::class,
                'columns' => ['number'],
            ],
            'project' => [
                'label' => 'Project',
                'model' => Project::class,
                'columns' => ['name', 'project_number', 'code'],
            ],
            'opportunity' => [
                'label' => 'Opportunity',
                'model' => Opportunity::class,
                'columns' => ['title', 'solicitation_number'],
            ],
            'company' => [
                'label' => 'Client / Company',
                'model' => Company::class,
                'columns' => ['name'],
            ],
            'contact' => [
                'label' => 'Contact',
                'model' => Contact::class,
                'columns' => ['first_name', 'last_name', 'email'],
            ],
            'invoice' => [
                'label' => 'Invoice / Estimate',
                'model' => Invoice::class,
                'columns' => ['number'],
            ],
            'supplier' => [
                'label' => 'Supplier / Vendor',
                'model' => Supplier::class,
                'columns' => ['name', 'code'],
            ],
        ];
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::map());
    }

    public static function labelForType(string $type): string
    {
        return self::map()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /** Short type key for a model instance/class, or null if it isn't a target. */
    public static function typeFor(Model|string $model): ?string
    {
        $class = is_string($model) ? $model : $model::class;
        foreach (self::map() as $type => $meta) {
            if ($meta['model'] === $class) {
                return $type;
            }
        }

        return null;
    }

    /** Org-scoped fetch of a single linked record (null if gone / wrong org). */
    public static function resolve(string $type, int $id, int $organizationId): ?Model
    {
        if (! self::has($type)) {
            return null;
        }
        $model = self::map()[$type]['model'];

        return $model::query()->where('organization_id', $organizationId)->find($id);
    }

    /**
     * Org-scoped typeahead over a target type.
     *
     * @return array<int, array{id:int, label:string}>
     */
    public static function search(string $type, string $term, int $organizationId, int $limit = 20): array
    {
        if (! self::has($type)) {
            return [];
        }
        $meta = self::map()[$type];
        $model = $meta['model'];

        $query = $model::query()->where('organization_id', $organizationId);
        $term = trim($term);
        if ($term !== '') {
            $query->where(function ($w) use ($meta, $term) {
                foreach (array_values($meta['columns']) as $i => $col) {
                    $w->{$i === 0 ? 'where' : 'orWhere'}($col, 'like', "%{$term}%");
                }
            });
        }

        return $query->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Model $m) => ['id' => (int) $m->getKey(), 'label' => self::label($type, $m)])
            ->all();
    }

    /** Display label for a resolved target row. */
    public static function label(string $type, Model $m): string
    {
        return match ($type) {
            'proposal' => trim(((string) ($m->proposal_number ?? '')) . ' — ' . ($m->project_name ?? 'Proposal'), ' —'),
            'purchase_order' => (string) ($m->number ?? ('PO #' . $m->getKey())),
            'project' => trim(((string) ($m->project_number ?? $m->code ?? '')) . ' — ' . ($m->name ?? 'Project'), ' —'),
            'opportunity' => (string) ($m->title ?? $m->solicitation_number ?? ('Opportunity #' . $m->getKey())),
            'company' => (string) ($m->name ?? ('Company #' . $m->getKey())),
            'contact' => trim((string) ($m->full_name ?? '')) ?: ('Contact #' . $m->getKey()),
            'invoice' => trim(ucfirst((string) ($m->kind ?? 'invoice')) . ' ' . ($m->number ?? ('#' . $m->getKey()))),
            'supplier' => trim((string) ($m->name ?? '') . ' ' . ($m->code ? "({$m->code})" : '')) ?: ('Supplier #' . $m->getKey()),
            default => 'Item #' . $m->getKey(),
        };
    }

    /** Best-effort in-app URL for a linked record, or null when the route is uncertain. */
    public static function url(string $type, int $id): ?string
    {
        return match ($type) {
            'proposal' => "/proposals/{$id}",
            'purchase_order' => "/procurement/purchase-orders/{$id}",
            'project' => "/projects/{$id}",
            'opportunity' => "/opportunities/{$id}",
            'supplier' => "/procurement/suppliers/{$id}",
            default => null,
        };
    }

    /** The picker options for the frontend: [{type,label}]. */
    public static function options(): array
    {
        return array_map(
            fn ($type, $meta) => ['type' => $type, 'label' => $meta['label']],
            array_keys(self::map()),
            array_values(self::map()),
        );
    }
}
