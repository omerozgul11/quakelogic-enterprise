<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProposalSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $proposals = ProposalSubmission::where('organization_id', $request->user()->organization_id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($proposals);
    }

    public function show(Request $request, ProposalSubmission $proposalSubmission): JsonResponse
    {
        abort_unless($proposalSubmission->organization_id === $request->user()->organization_id, 403);
        return response()->json($proposalSubmission->load(['files', 'teamMembers']));
    }
}
