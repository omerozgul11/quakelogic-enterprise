<?php

namespace App\Modules\ServiceDesk\Http\Requests;

use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class TicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', new Enum(TicketType::class)],
            'priority' => ['required', new Enum(TicketPriority::class)],
            'channel' => ['nullable', 'string', 'in:email,phone,portal,web'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'rma_disposition' => ['nullable', 'string', 'in:repair,replace,refund,reject'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $orgId)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'asset_id' => ['nullable', Rule::exists('asset_assets', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'inventory_product_id' => ['nullable', Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->type ?: TicketType::Support->value,
            'priority' => $this->priority ?: TicketPriority::Normal->value,
        ]);
    }
}
