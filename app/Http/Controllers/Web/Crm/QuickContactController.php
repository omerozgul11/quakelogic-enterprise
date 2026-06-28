<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\QuickContactCategory;
use App\Http\Controllers\Controller;
use App\Models\Crm\QuickContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quick Contacts — a shared organization rolodex of frequently-dialed numbers
 * (bank desks, carriers, agencies). Viewable by anyone with CRM access; editing
 * is gated by the same `manage contacts` permission as the people directory.
 */
class QuickContactController extends Controller
{
    private const MANAGE = 'manage contacts';

    public function index(Request $request): Response
    {
        $user = $request->user();

        $contacts = QuickContact::forOrganization($user->organization_id)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('organization_name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('notes', 'like', "%{$s}%")))
            ->when(
                $request->category && in_array($request->category, array_column(QuickContactCategory::cases(), 'value'), true),
                fn ($q) => $q->where('category', $request->category)
            )
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (QuickContact $c) => $this->shape($c));

        return Inertia::render('Crm/QuickContacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search', 'category']),
            'categories' => QuickContactCategory::options(),
            'can' => ['manage' => $user->can(self::MANAGE)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can(self::MANAGE), 403);

        QuickContact::create([
            ...$this->validateData($request),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Quick contact added.');
    }

    public function update(Request $request, QuickContact $quickContact): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can(self::MANAGE) && $quickContact->organization_id === $user->organization_id,
            403
        );

        $quickContact->update($this->validateData($request));

        return back()->with('success', 'Quick contact updated.');
    }

    public function destroy(Request $request, QuickContact $quickContact): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user->can(self::MANAGE) && $quickContact->organization_id === $user->organization_id,
            403
        );

        $quickContact->delete();

        return back()->with('success', 'Quick contact removed.');
    }

    /** @return array<string,mixed> */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'organization_name' => ['nullable', 'string', 'max:150'],
            'category' => ['required', Rule::enum(QuickContactCategory::class)],
            'phone' => ['nullable', 'string', 'max:40'],
            'extension' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_pinned' => ['boolean'],
        ]);
    }

    /** @return array<string,mixed> */
    private function shape(QuickContact $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'organization_name' => $c->organization_name,
            'category' => $c->category->value,
            'category_label' => $c->category->label(),
            'category_color' => $c->category->color(),
            'phone' => $c->phone,
            'extension' => $c->extension,
            'email' => $c->email,
            'website' => $c->website,
            'notes' => $c->notes,
            'is_pinned' => $c->is_pinned,
        ];
    }
}
