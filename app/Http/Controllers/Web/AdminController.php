<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    public function users(Request $request): Response
    {
        return $this->index($request);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'ulid' => (string) Str::ulid(),
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User created.');
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

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        $user->delete();

        return back()->with('success', 'User deleted.');
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
