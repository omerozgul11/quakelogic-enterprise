<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Enums\ProductType;
use App\Support\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level policy authorization is enforced in the controller.
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $productId = $this->route('product')?->id;

        return [
            'sku' => [
                'required', 'string', 'max:80',
                Rule::unique('inventory_products', 'sku')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ProductType::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            // Product photo — jpeg/png/webp/gif, up to 5 MB.
            'image' => ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'unit_of_measure' => ['nullable', 'string', 'max:30'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:160'],
            'mpn' => ['nullable', 'string', 'max:120'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'reorder_point' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'is_serialized' => ['boolean'],
            'track_inventory' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_serialized' => $this->boolean('is_serialized'),
            'track_inventory' => $this->has('track_inventory') ? $this->boolean('track_inventory') : true,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'remove_image' => $this->boolean('remove_image'),
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
        ]);
    }
}
