<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'procurement_supplier_id' => [
                'required',
                Rule::exists('procurement_suppliers', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'inventory_warehouse_id' => [
                'nullable',
                Rule::exists('inventory_warehouses', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.inventory_product_id' => [
                'nullable',
                Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
            'tax_rate' => $this->tax_rate ?? 0,
            'shipping_amount' => $this->shipping_amount ?? 0,
        ]);
    }
}
