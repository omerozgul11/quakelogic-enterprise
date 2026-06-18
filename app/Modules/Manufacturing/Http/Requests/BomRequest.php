<?php

namespace App\Modules\Manufacturing\Http\Requests;

use App\Modules\Manufacturing\Enums\BomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $product = Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at');

        return [
            'inventory_product_id' => ['required', $product],
            'name' => ['required', 'string', 'max:160'],
            'version' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', new Enum(BomStatus::class)],
            'output_quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_product_id' => ['required', $product],
            'items.*.quantity_per' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?: BomStatus::Active->value,
            'version' => $this->version ?: 'v1',
            'output_quantity' => $this->output_quantity ?: 1,
            'is_default' => $this->boolean('is_default'),
        ]);
    }
}
