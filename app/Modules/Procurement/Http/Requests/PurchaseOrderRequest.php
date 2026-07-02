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
                'nullable',
                'required_without:new_supplier.name',
                Rule::exists('procurement_suppliers', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],

            // Inline "supplier doesn't exist yet" creation: supply either an
            // existing supplier id above, or a new supplier's name (+ optional
            // details) here. The controller creates it alongside the PO.
            'new_supplier' => ['nullable', 'array'],
            'new_supplier.name' => ['nullable', 'required_without:procurement_supplier_id', 'string', 'max:255'],
            'new_supplier.code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('procurement_suppliers', 'code')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'new_supplier.category' => ['nullable', 'string', 'max:120'],
            'new_supplier.email' => ['nullable', 'email', 'max:255'],
            'new_supplier.phone' => ['nullable', 'string', 'max:40'],
            'new_supplier.website' => ['nullable', 'string', 'max:255'],
            'new_supplier.payment_terms' => ['nullable', 'string', 'max:60'],
            'new_supplier.tax_id' => ['nullable', 'string', 'max:60'],
            'new_supplier.address_line1' => ['nullable', 'string', 'max:255'],
            'new_supplier.city' => ['nullable', 'string', 'max:120'],
            'new_supplier.state' => ['nullable', 'string', 'max:120'],
            'new_supplier.postal_code' => ['nullable', 'string', 'max:30'],
            'new_supplier.country' => ['nullable', 'string', 'max:120'],
            'new_supplier.notes' => ['nullable', 'string'],
            'inventory_warehouse_id' => [
                'nullable',
                Rule::exists('inventory_warehouses', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            // Optional client (CRM company) the purchase is for — not the supplier.
            'company_id' => [
                'nullable',
                Rule::exists('companies', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Tax may be entered as a rate (above) OR as a flat amount here; a
            // non-zero rate takes precedence, otherwise this fixed amount is used.
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'shipping_terms' => ['nullable', 'string', 'max:120'],
            'use_ql_shipping_account' => ['nullable', 'boolean'],

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

    /** @return array<string,string> */
    public function attributes(): array
    {
        return [
            'new_supplier.name' => 'supplier name',
            'new_supplier.code' => 'supplier code',
            'new_supplier.email' => 'supplier email',
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'procurement_supplier_id.required_without' => 'Select a supplier, or add a new one.',
            'new_supplier.name.required_without' => 'Enter the new supplier’s name.',
        ];
    }
}
