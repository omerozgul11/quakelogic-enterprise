<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:120'],
            'requester_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'crm_project_id' => ['nullable', Rule::exists('crm_projects', 'id')->where('organization_id', $orgId)],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.inventory_product_id' => [
                'nullable',
                Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => $this->currency ? strtoupper($this->currency) : 'USD']);
    }
}
