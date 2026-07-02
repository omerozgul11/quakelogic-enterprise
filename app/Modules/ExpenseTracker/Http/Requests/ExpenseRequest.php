<?php

namespace App\Modules\ExpenseTracker\Http\Requests;

use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $scoped = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $orgId);

        return [
            'vendor' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'expense_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'is_billable' => ['boolean'],
            'receipt' => ['nullable', 'file', 'max:25600', 'mimetypes:application/pdf,image/jpeg,image/png,image/heic,image/heif'],
            'expense_category_id' => ['nullable', $scoped('expense_categories')],
            'company_id' => ['nullable', $scoped('companies')],
            'crm_project_id' => ['nullable', $scoped('crm_projects')],
            'proposal_id' => ['nullable', $scoped('proposal_submissions')],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
            'is_billable' => $this->boolean('is_billable'),
        ]);
    }
}
