<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Leave;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Records team leave (time-off) for the team-presence strip on the CRM
 * dashboard. Marking someone on leave is a management action, gated by the same
 * `manage all time cards` permission used for cross-user time cards.
 */
class LeaveController extends Controller
{
    private const MANAGE = 'manage all time cards';

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can(self::MANAGE), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Never let a manager mark someone outside their own organization.
        $inOrg = User::where('organization_id', $actor->organization_id)
            ->where('id', $data['user_id'])->exists();
        abort_unless($inOrg, 403);

        Leave::create([
            'organization_id' => $actor->organization_id,
            'user_id' => $data['user_id'],
            'created_by' => $actor->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'type' => $data['type'] ?: 'vacation',
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Leave recorded.');
    }

    public function destroy(Request $request, Leave $leave): RedirectResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor->can(self::MANAGE) && $leave->organization_id === $actor->organization_id,
            403
        );

        $leave->delete();

        return back()->with('success', 'Leave removed.');
    }
}
