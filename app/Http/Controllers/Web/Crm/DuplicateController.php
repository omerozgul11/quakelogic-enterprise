<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Services\Crm\MergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finds likely-duplicate companies and contacts and merges them on request.
 * Detection is heuristic (normalized name, shared email); the merge itself is
 * handled by {@see MergeService} and is soft-delete only.
 */
class DuplicateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);
        $orgId = $request->user()->organization_id;

        return Inertia::render('Crm/Duplicates/Index', [
            'companyGroups' => $this->companyGroups($orgId),
            'contactGroups' => $this->contactGroups($orgId),
        ]);
    }

    public function merge(Request $request, MergeService $merge): RedirectResponse
    {
        $this->authorize('create', Company::class);
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'type' => ['required', Rule::in(['company', 'contact'])],
            'primary_id' => ['required', 'integer'],
            'duplicate_ids' => ['required', 'array', 'min:1'],
            'duplicate_ids.*' => ['integer', 'different:primary_id'],
        ]);

        $model = $data['type'] === 'company' ? Company::class : Contact::class;

        $primary = $model::where('organization_id', $orgId)->findOrFail($data['primary_id']);
        $duplicates = $model::where('organization_id', $orgId)
            ->whereIn('id', $data['duplicate_ids'])->get();

        foreach ($duplicates as $duplicate) {
            $data['type'] === 'company'
                ? $merge->mergeCompanies($primary, $duplicate)
                : $merge->mergeContacts($primary, $duplicate);
        }

        return back()->with('success', $duplicates->count().' record(s) merged.');
    }

    /** @return array<int, array<string,mixed>> */
    private function companyGroups(int $orgId): array
    {
        $companies = Company::where('organization_id', $orgId)
            ->get(['id', 'name', 'email', 'phone', 'website', 'created_at']);

        $leadCounts = DB::table('crm_leads')->where('organization_id', $orgId)->whereNull('deleted_at')
            ->select('company_id', DB::raw('count(*) as c'))->groupBy('company_id')->pluck('c', 'company_id');
        $contactCounts = DB::table('contacts')->where('organization_id', $orgId)->whereNull('deleted_at')
            ->select('company_id', DB::raw('count(*) as c'))->groupBy('company_id')->pluck('c', 'company_id');

        return $companies
            ->groupBy(fn (Company $c) => $this->normalize($c->name))
            ->filter(fn (Collection $g) => $g->count() > 1)
            ->map(fn (Collection $g, string $key) => [
                'key' => $key,
                'label' => $g->first()->name,
                'members' => $g->map(fn (Company $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'detail' => $c->email ?: $c->phone ?: $c->website,
                    'related' => (int) ($leadCounts[$c->id] ?? 0) + (int) ($contactCounts[$c->id] ?? 0),
                    'created_at' => $c->created_at?->toDateString(),
                ])->values(),
            ])->values()->all();
    }

    /** @return array<int, array<string,mixed>> */
    private function contactGroups(int $orgId): array
    {
        $contacts = Contact::where('organization_id', $orgId)
            ->with('company:id,name')
            ->get(['id', 'first_name', 'last_name', 'email', 'company_id']);

        $leadCounts = DB::table('crm_leads')->where('organization_id', $orgId)->whereNull('deleted_at')
            ->select('contact_id', DB::raw('count(*) as c'))->groupBy('contact_id')->pluck('c', 'contact_id');

        // Signature: shared email, else normalized full-name within the same company.
        $signature = function (Contact $c): ?string {
            if (filled($c->email)) {
                return 'e:'.strtolower(trim($c->email));
            }
            $name = $this->normalize("{$c->first_name} {$c->last_name}");

            return $name && $c->company_id ? "n:{$name}:{$c->company_id}" : null;
        };

        return $contacts
            ->groupBy($signature)
            ->filter(fn (Collection $g, $key) => $key && $g->count() > 1)
            ->map(fn (Collection $g, string $key) => [
                'key' => $key,
                'label' => filled($g->first()->email) ? $g->first()->email : trim("{$g->first()->first_name} {$g->first()->last_name}"),
                'members' => $g->map(fn (Contact $c) => [
                    'id' => $c->id,
                    'name' => trim("{$c->first_name} {$c->last_name}"),
                    'detail' => $c->company?->name ?: $c->email,
                    'related' => (int) ($leadCounts[$c->id] ?? 0),
                    'created_at' => null,
                ])->values(),
            ])->values()->all();
    }

    /** Lowercase, drop punctuation/legal suffixes and collapse whitespace for grouping. */
    private function normalize(string $name): string
    {
        $s = strtolower($name);
        $s = preg_replace('/\b(inc|llc|ltd|corp|co|company|gmbh|the)\b/', ' ', $s) ?? $s;
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? '');
    }
}
