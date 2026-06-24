<?php

namespace App\Modules\ExpenseTracker\Http\Requests;

use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Enums\RecurringFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RecurringExpenseRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'vendor' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'is_billable' => ['boolean'],
            'frequency' => ['required', new Enum(RecurringFrequency::class)],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_approve' => ['boolean'],
            'is_active' => ['boolean'],
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
            'interval_count' => $this->interval_count ?: 1,
            'is_billable' => $this->boolean('is_billable'),
            'auto_approve' => $this->boolean('auto_approve'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
