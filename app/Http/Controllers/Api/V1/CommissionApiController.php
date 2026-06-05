<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Commission::where('organization_id', $request->user()->organization_id);

        if (!$request->user()->can('view all commissions')) {
            $query->where('user_id', $request->user()->id);
        }

        $commissions = $query->with(['user:id,name', 'proposal:id,proposal_number,project_name'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($commissions);
    }

    public function show(Request $request, Commission $commission): JsonResponse
    {
        abort_unless($commission->organization_id === $request->user()->organization_id, 403);

        if (!$request->user()->can('view all commissions') && $commission->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($commission->load(['user:id,name', 'proposal:id,proposal_number,project_name']));
    }
}
