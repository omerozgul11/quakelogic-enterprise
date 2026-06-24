<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Activity;
use App\Models\Crm\Lead;
use App\Services\Crm\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manual timeline entries (note / call / email / meeting) logged by a user
 * against a CRM record. System entries (stage changes, conversions) are written
 * directly by the owning controllers via {@see ActivityLogger}.
 */
class ActivityController extends Controller
{
    /** Whitelist of records a timeline can attach to, by short key. */
    private const SUBJECTS = [
        'lead' => Lead::class,
        'company' => Company::class,
        'contact' => Contact::class,
    ];

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(Activity::MANUAL_TYPES)],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $model = self::SUBJECTS[$data['subject']];
        $record = $model::where('organization_id', $user->organization_id)
            ->findOrFail($data['subject_id']);

        $logger->log($record, $data['type'], $data['body'], $user);

        return back()->with('success', 'Activity logged.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($activity->organization_id === $user->organization_id, 403);
        // Only the author may remove their own manual entry; system entries stay.
        abort_unless(
            $activity->user_id === $user->id && in_array($activity->type, Activity::MANUAL_TYPES, true),
            403
        );

        $activity->delete();

        return back()->with('success', 'Activity removed.');
    }
}
