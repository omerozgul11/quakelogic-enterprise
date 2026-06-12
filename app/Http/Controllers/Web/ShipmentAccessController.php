<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shipments admin panel (/shipments/admin) — admins only. Controls each user's
 * access to the SHIPMENTS section by toggling a DIRECT `access shipments`
 * permission on the user. This touches only that one permission: roles and all
 * Proposals permissions are untouched, so a user's Proposals access never
 * changes. Users themselves are the shared accounts (same `users` table).
 */
class ShipmentAccessController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $users = User::query()
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get()
            ->map(function (User $u) {
                $isAdmin = $u->hasRole('Super Admin');

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->getRoleNames()->first() ?? '—',
                    'is_admin' => $isAdmin,
                    'is_active' => (bool) $u->is_active,
                    // Admins always have access (via their role); everyone else's
                    // access is the direct permission this panel controls.
                    'has_access' => $isAdmin ? true : $u->hasDirectPermission('access shipments'),
                ];
            });

        return Inertia::render('Shipments/Admin', [
            'users' => $users,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $request->user()->organization_id, 403);

        $data = $request->validate(['grant' => ['required', 'boolean']]);

        if ($user->hasRole('Super Admin')) {
            return back()->with('warning', 'Admins always have Shipments access.');
        }

        // Touches ONLY the `access shipments` permission — never roles or any
        // Proposals permission, so Proposals access is unaffected.
        if ($data['grant']) {
            $user->givePermissionTo('access shipments');
        } else {
            $user->revokePermissionTo('access shipments');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', $user->name.($data['grant']
            ? ' can now access Shipments.'
            : ' no longer has Shipments access.'));
    }
}
