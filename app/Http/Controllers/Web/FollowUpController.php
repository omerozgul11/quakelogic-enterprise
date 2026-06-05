<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = FollowUp::where('organization_id', $user->organization_id)
            ->with(['assignedTo:id,name', 'proposal:id,proposal_number,project_name', 'contact:id,first_name,last_name'])
            ->orderBy('scheduled_date');

        if (!$user->can('view all proposals')) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('FollowUps/Index', [
            'followUps' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'subject' => 'required|string|max:255',
            'message' => 'nullable|string',
            'scheduled_date' => 'required|date',
            'assigned_to' => 'nullable|exists:users,id',
            'proposal_submission_id' => 'nullable|exists:proposal_submissions,id',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        $user = $request->user();
        FollowUp::create([...$validated, 'organization_id' => $user->organization_id, 'created_by' => $user->id, 'status' => 'scheduled']);

        return back()->with('success', 'Follow-up scheduled.');
    }

    public function update(Request $request, FollowUp $followUp): RedirectResponse
    {
        abort_unless($followUp->organization_id === $request->user()->organization_id, 403);

        $validated = $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $followUp->update($validated);

        if ($validated['status'] === 'sent') {
            $followUp->update(['sent_at' => now()]);
        } elseif ($validated['status'] === 'responded') {
            $followUp->update(['responded_at' => now()]);
        }

        return back()->with('success', 'Follow-up updated.');
    }
}
