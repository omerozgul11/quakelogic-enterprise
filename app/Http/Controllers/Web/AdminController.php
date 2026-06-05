<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::where('organization_id', $request->user()->organization_id)
            ->with('roles')
            ->orderBy('name')
            ->paginate(25);

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User updated.');
    }

    public function auditLogs(Request $request): Response
    {
        $logs = AuditLog::where('organization_id', $request->user()->organization_id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('Admin/AuditLogs', ['logs' => $logs]);
    }
}
