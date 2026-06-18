<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Procurement\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $supplierId = $this->route('supplier')?->id;

        return [
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('procurement_suppliers', 'code')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
                    ->ignore($supplierId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', new Enum(SupplierStatus::class)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:120'],
            'payment_terms' => ['nullable', 'string', 'max:60'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?: SupplierStatus::Active->value,
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
        ]);
    }
}
