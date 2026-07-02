<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalDocumentType;
use App\Modules\Procurement\Models\ApprovalFlow;
use App\Modules\Procurement\Models\ApprovalFlowStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Admin configuration of multi-level approval chains (gated by
 * `manage approval flows`). A flow is an amount-tiered, ordered set of steps for
 * a document type; steps assign a specific user or a role, and can require a
 * digital signature.
 */
class ApprovalFlowController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $flows = ApprovalFlow::where('organization_id', $orgId)
            ->with(['steps.approver:id,name'])
            ->orderBy('document_type')->orderBy('min_amount')->orderByDesc('id')
            ->get()
            ->map(fn (ApprovalFlow $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'document_type' => $f->document_type->value,
                'document_type_label' => $f->document_type->label(),
                'min_amount' => (float) $f->min_amount,
                'is_active' => $f->is_active,
                'steps' => $f->steps->map(fn (ApprovalFlowStep $s) => [
                    'name' => $s->name,
                    'approver_type' => $s->approver_type,
                    'approver_user_id' => $s->approver_user_id,
                    'approver_user' => $s->approver?->name,
                    'approver_role' => $s->approver_role,
                    'require_signature' => $s->require_signature,
                ])->values(),
            ]);

        return Inertia::render('Procurement/ApprovalFlows/Index', [
            'flows' => $flows,
            'documentTypes' => ApprovalDocumentType::options(),
            'users' => User::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(fn () => $this->persist(new ApprovalFlow(['organization_id' => $request->user()->organization_id, 'created_by' => $request->user()->id]), $data));

        return back()->with('success', 'Approval flow created.');
    }

    public function update(Request $request, ApprovalFlow $approvalFlow): RedirectResponse
    {
        abort_unless($approvalFlow->organization_id === $request->user()->organization_id, 404);
        $data = $this->validated($request);
        DB::transaction(function () use ($approvalFlow, $data) {
            $approvalFlow->steps()->delete();
            $this->persist($approvalFlow, $data);
        });

        return back()->with('success', 'Approval flow updated.');
    }

    public function destroy(Request $request, ApprovalFlow $approvalFlow): RedirectResponse
    {
        abort_unless($approvalFlow->organization_id === $request->user()->organization_id, 404);
        $approvalFlow->delete();

        return back()->with('success', 'Approval flow deleted.');
    }

    private function validated(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'document_type' => ['required', Rule::in(array_column(ApprovalDocumentType::cases(), 'value'))],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'is_active' => ['boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:20'],
            'steps.*.name' => ['nullable', 'string', 'max:120'],
            'steps.*.approver_type' => ['required', Rule::in(['user', 'role'])],
            'steps.*.approver_user_id' => ['nullable', 'required_if:steps.*.approver_type,user', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'steps.*.approver_role' => ['nullable', 'required_if:steps.*.approver_type,role', 'string', Rule::exists('roles', 'name')],
            'steps.*.require_signature' => ['boolean'],
        ]);
    }

    private function persist(ApprovalFlow $flow, array $data): void
    {
        $flow->fill([
            'name' => $data['name'],
            'document_type' => $data['document_type'],
            'min_amount' => $data['min_amount'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        foreach (array_values($data['steps']) as $i => $step) {
            $isUser = $step['approver_type'] === 'user';
            $flow->steps()->create([
                'organization_id' => $flow->organization_id,
                'position' => $i,
                'name' => $step['name'] ?? null,
                'approver_type' => $step['approver_type'],
                'approver_user_id' => $isUser ? $step['approver_user_id'] : null,
                'approver_role' => $isUser ? null : $step['approver_role'],
                'require_signature' => (bool) ($step['require_signature'] ?? false),
            ]);
        }
    }
}
