<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillRequest extends FormRequest
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
            'procurement_purchase_order_id' => [
                'nullable',
                Rule::exists('procurement_purchase_orders', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'vendor_invoice_number' => ['nullable', 'string', 'max:120'],
            'bill_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'discount_total' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],

            'recurring' => ['nullable', 'boolean'],
            'recurring_frequency' => ['nullable', 'required_if:recurring,true', Rule::in(['weekly', 'monthly', 'quarterly', 'yearly'])],
            'recurring_total_cycles' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'next_recurring_date' => ['nullable', 'required_if:recurring,true', 'date'],

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
            'shipping_amount' => $this->shipping_amount ?? 0,
            'discount_total' => $this->discount_total ?? 0,
            'recurring' => filter_var($this->recurring, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
