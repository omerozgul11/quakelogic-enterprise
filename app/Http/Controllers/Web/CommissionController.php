<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\ProposalSubmission;
use App\Services\Commissions\CommissionCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionCalculationService $calculator) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Commission::class);
        $user = $request->user();

        $query = Commission::where('organization_id', $user->organization_id)
            ->with(['user:id,name', 'proposal:id,proposal_number,project_name,award_value'])
            ->orderByDesc('created_at');

        if (!$user->can('view all commissions')) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('user_id') && $user->can('view all commissions')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('period_month')) {
            $query->where('period_month', $request->period_month);
        }

        $commissions = $query->paginate(25)->withQueryString();

        $totalAmount = (float) $query->sum('commission_amount');

        return Inertia::render('Commissions/Index', [
            'commissions' => $commissions,
            'totalAmount' => $totalAmount,
            'summary' => [
                'total' => $totalAmount,
                'pending' => (float) (clone $query)->where('status', 'pending')->sum('commission_amount'),
                'approved' => (float) (clone $query)->where('status', 'approved')->sum('commission_amount'),
            ],
            'filters' => $request->only(['user_id', 'period_month', 'status']),
            'can' => [
                'viewAll' => $user->can('view all commissions'),
                'manage' => $user->can('manage commission rules'),
                'approve' => $user->can('approve commissions'),
            ],
        ]);
    }

    public function rules(Request $request): Response
    {
        $this->authorize('manage', Commission::class);

        $rules = CommissionRule::where('organization_id', $request->user()->organization_id)
            ->with('user:id,name')
            ->orderByDesc('effective_from')
            ->get();

        return Inertia::render('Commissions/Rules', [
            'rules' => $rules,
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $this->authorize('manage', Commission::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed_amount,percentage,tiered',
            'rate' => 'nullable|numeric|min:0|max:100',
            'fixed_amount' => 'nullable|numeric|min:0',
            'base_on' => 'required|in:proposal_value,award_value,margin',
            'user_id' => 'nullable|exists:users,id',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'tier_config' => 'nullable|array',
        ]);

        CommissionRule::create([...$validated, 'organization_id' => $request->user()->organization_id]);

        return redirect()->route('commissions.rules')->with('success', 'Commission rule created.');
    }

    public function approve(Request $request, Commission $commission): RedirectResponse
    {
        $this->authorize('approve', $commission);

        $commission->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Commission approved.');
    }
}
