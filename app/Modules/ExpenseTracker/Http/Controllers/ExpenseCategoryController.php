<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseTracker\Http\Requests\ExpenseCategoryRequest;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExpenseCategory::class);
        $orgId = $request->user()->organization_id;

        $categories = ExpenseCategory::where('organization_id', $orgId)
            ->withCount('expenses')
            ->orderBy('name')->get()
            ->map(function (ExpenseCategory $c) {
                $spent = $c->spentThisPeriod();
                $budget = $c->budget_amount !== null ? (float) $c->budget_amount : null;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'color' => $c->color,
                    'budget_amount' => $budget,
                    'budget_period' => $c->budget_period,
                    'currency' => $c->currency,
                    'is_active' => $c->is_active,
                    'expenses_count' => $c->expenses_count,
                    'spent_this_period' => round($spent, 2),
                    'over_budget' => $budget !== null && $spent > $budget,
                    'pct' => $budget && $budget > 0 ? min(100, round($spent / $budget * 100)) : null,
                ];
            });

        return Inertia::render('Expenses/Categories/Index', [
            'categories' => $categories,
            'can' => ['manage' => $request->user()->can('manage expenses')],
        ]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);
        $user = $request->user();

        ExpenseCategory::create([
            ...$request->validated(),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $category->update($request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->expenses()->exists()) {
            return back()->with('error', 'Cannot delete a category that still has expenses. Deactivate it instead.');
        }

        $name = $category->name;
        $category->delete();

        return back()->with('success', "Category \"{$name}\" deleted.");
    }
}
