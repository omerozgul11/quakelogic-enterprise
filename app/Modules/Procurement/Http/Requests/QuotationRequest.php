<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
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
            'procurement_purchase_request_id' => [
                'nullable',
                Rule::exists('procurement_purchase_requests', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'quote_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'discount_total' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'vendor_note' => ['nullable', 'string'],
            'admin_note' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.inventory_product_id' => [
                'nullable',
                Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
            'discount_total' => $this->discount_total ?? 0,
        ]);
    }
}
