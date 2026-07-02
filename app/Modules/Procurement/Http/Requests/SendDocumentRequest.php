<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the "send document to vendor" modal payload used by PR / RFQ / PO
 * send actions. Authorization is handled by the controller (policy check on the
 * parent document), so this only validates shape.
 */
class SendDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'array', 'max:20'],
            'cc.*' => ['email', 'max:255'],
            'bcc' => ['nullable', 'array', 'max:20'],
            'bcc.*' => ['email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Accept CC/BCC as either arrays or comma/semicolon-separated strings from
     * the modal, normalizing to arrays before validation.
     */
    protected function prepareForValidation(): void
    {
        foreach (['cc', 'bcc'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([
                    $field => array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value) ?: []))),
                ]);
            }
        }
    }
}
