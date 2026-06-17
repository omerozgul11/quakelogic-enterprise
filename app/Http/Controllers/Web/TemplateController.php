<?php

namespace App\Http\Controllers\Web;

use App\Enums\TemplateCategory;
use App\Http\Controllers\Controller;
use App\Models\ProposalTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 7 — proposal template library. Reusable content blocks (company
 * profile, technical narrative, QA/QC, warranty, training/installation/support
 * plans) writers can copy into proposals.
 */
class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $templates = ProposalTemplate::forOrganization($user->organization_id)
            ->with('createdBy:id,name')
            ->orderBy('category')
            ->orderBy('title')
            ->get()
            ->map(fn (ProposalTemplate $t) => [
                'id' => $t->id,
                'category' => $t->category->value,
                'category_label' => $t->category->label(),
                'title' => $t->title,
                'content' => $t->content,
                'is_active' => $t->is_active,
                'author' => $t->createdBy?->name,
                'updated_at' => $t->updated_at?->toIso8601String(),
            ])->values();

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'categories' => array_map(fn (TemplateCategory $c) => ['value' => $c->value, 'label' => $c->label()], TemplateCategory::cases()),
            'can' => ['manage' => $user->can('manage templates')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage templates');
        $data = $this->validated($request);
        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by'] = $request->user()->id;

        ProposalTemplate::create($data);

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, ProposalTemplate $template): RedirectResponse
    {
        $this->authorize('manage templates');
        abort_unless($template->organization_id === $request->user()->organization_id, 404);

        $template->update($this->validated($request));

        return back()->with('success', 'Template updated.');
    }

    public function destroy(Request $request, ProposalTemplate $template): RedirectResponse
    {
        $this->authorize('manage templates');
        abort_unless($template->organization_id === $request->user()->organization_id, 404);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_map(fn ($c) => $c->value, TemplateCategory::cases()))],
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
    }
}
