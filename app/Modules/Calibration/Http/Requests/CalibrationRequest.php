<?php

namespace App\Modules\Calibration\Http\Requests;

use App\Modules\Calibration\Enums\CalibrationResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CalibrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $certId = $this->route('certificate')?->id;

        return [
            'certificate_number' => [
                'nullable', 'string', 'max:60',
                Rule::unique('calibration_certificates', 'certificate_number')->where('organization_id', $orgId)->whereNull('deleted_at')->ignore($certId),
            ],
            'asset_id' => ['nullable', Rule::exists('asset_assets', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'inventory_product_id' => ['nullable', Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'performed_by' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'result' => ['required', new Enum(CalibrationResult::class)],
            'nist_traceable' => ['boolean'],
            'method' => ['nullable', 'string', 'max:255'],
            'standard_used' => ['nullable', 'string', 'max:255'],
            'technician' => ['nullable', 'string', 'max:160'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'calibrated_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date'],
            'interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nist_traceable' => $this->has('nist_traceable') ? $this->boolean('nist_traceable') : true,
            'result' => $this->result ?: CalibrationResult::Pass->value,
        ]);
    }
}
