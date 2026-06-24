<?php

namespace App\Modules\ExpenseTracker\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $orgId = $this->user()->organization_id;
        $categoryId = $this->route('category')?->id;

        return [
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('expense_categories', 'name')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at')
                    ->ignore($categoryId),
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'budget_period' => ['nullable', 'in:monthly,quarterly,yearly'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'budget_period' => $this->budget_period ?: 'monthly',
            'currency' => $this->currency ? strtoupper($this->currency) : 'USD',
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
