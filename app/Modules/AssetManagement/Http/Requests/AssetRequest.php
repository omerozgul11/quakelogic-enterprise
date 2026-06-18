<?php

namespace App\Modules\AssetManagement\Http\Requests;

use App\Modules\AssetManagement\Enums\AssetStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $assetId = $this->route('asset')?->id;

        return [
            'asset_tag' => [
                'required', 'string', 'max:60',
                Rule::unique('asset_assets', 'asset_tag')->where('organization_id', $orgId)->whereNull('deleted_at')->ignore($assetId),
            ],
            'name' => ['required', 'string', 'max:200'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', new Enum(AssetStatus::class)],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:200'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'condition' => ['nullable', 'string', 'in:new,good,fair,poor'],
            'inventory_product_id' => ['nullable', Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $orgId)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'current_value' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date'],
            'deployed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?: AssetStatus::InStock->value,
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
        ]);
    }
}
