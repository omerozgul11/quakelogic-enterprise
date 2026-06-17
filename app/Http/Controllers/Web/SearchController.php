<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'groups' => []]);
        }

        $org = $request->user()->organization_id;
        $like = '%' . $q . '%';
        $groups = [];

        $proposals = ProposalSubmission::where('organization_id', $org)
            ->where(fn ($w) => $w->where('project_name', 'like', $like)
                ->orWhere('proposal_number', 'like', $like)
                ->orWhere('solicitation_number', 'like', $like))
            ->latest()->limit(6)->get();
        if ($proposals->isNotEmpty()) {
            $groups[] = ['label' => 'Proposals', 'icon' => 'file-text', 'items' => $proposals->map(fn ($p) => [
                'label' => $p->project_name,
                'sub' => $p->proposal_number,
                'url' => "/proposals/{$p->id}",
            ])];
        }

        $opportunities = Opportunity::where('organization_id', $org)
            ->where(fn ($w) => $w->where('title', 'like', $like)
                ->orWhere('solicitation_number', 'like', $like)
                ->orWhere('agency_name', 'like', $like))
            ->latest()->limit(6)->get();
        if ($opportunities->isNotEmpty()) {
            $groups[] = ['label' => 'Opportunities', 'icon' => 'target', 'items' => $opportunities->map(fn ($o) => [
                'label' => $o->title,
                'sub' => $o->agency_name ?: $o->solicitation_number,
                'url' => "/opportunities/{$o->id}",
            ])];
        }

        $agencies = Agency::where('organization_id', $org)
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('acronym', 'like', $like))
            ->limit(5)->get();
        if ($agencies->isNotEmpty()) {
            $groups[] = ['label' => 'Agencies', 'icon' => 'building', 'items' => $agencies->map(fn ($a) => [
                'label' => $a->name,
                'sub' => $a->acronym,
                'url' => "/agencies/{$a->id}",
            ])];
        }

        $companies = Company::where('organization_id', $org)
            ->where('name', 'like', $like)->limit(5)->get();
        if ($companies->isNotEmpty()) {
            $groups[] = ['label' => 'Companies', 'icon' => 'building', 'items' => $companies->map(fn ($c) => [
                'label' => $c->name,
                'sub' => $c->company_type,
                'url' => "/companies/{$c->id}",
            ])];
        }

        $contacts = Contact::where('organization_id', $org)
            ->where(fn ($w) => $w->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like))
            ->limit(6)->get();
        if ($contacts->isNotEmpty()) {
            $groups[] = ['label' => 'Contacts', 'icon' => 'users', 'items' => $contacts->map(fn ($c) => [
                'label' => trim("{$c->first_name} {$c->last_name}"),
                'sub' => $c->email ?: $c->title,
                'url' => "/contacts/{$c->id}",
            ])];
        }

        return response()->json(['query' => $q, 'groups' => $groups]);
    }
}
