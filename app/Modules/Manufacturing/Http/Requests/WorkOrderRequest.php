<?php

namespace App\Modules\Manufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderRequest extends FormRequest
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
            'inventory_product_id' => [
                'required',
                Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'manufacturing_bom_id' => [
                'nullable',
                Rule::exists('manufacturing_boms', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'inventory_warehouse_id' => [
                'required',
                Rule::exists('inventory_warehouses', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'quantity_planned' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'scheduled_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
