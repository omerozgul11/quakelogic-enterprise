<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        // Notifications are scoped to the section you're in: Shipments alerts
        // (data.type = 'shipment') only show under /shipments; everything else
        // shows in Proposals. So the bell never mixes the two apps.
        $inShipments = $request->is('shipments') || $request->is('shipments/*');
        $scopeNotifications = function ($query) use ($inShipments) {
            return $inShipments
                ? $query->where('data->type', 'shipment')
                : $query->where(function ($q) {
                    $q->where('data->type', '!=', 'shipment')->orWhereNull('data->type');
                });
        };

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'title' => $user->title,
                    'avatar_url' => $user->avatar_url,
                    'organization_id' => $user->organization_id,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    // Guard against a partially-hydrated user model (e.g. a test
                    // factory user that never loaded this nullable column) so
                    // strict mode doesn't throw on a missing attribute.
                    'preferences' => \App\Http\Controllers\Web\SettingsController::mergedPreferences(
                        array_key_exists('notification_preferences', $user->getAttributes()) ? $user->notification_preferences : null
                    ),
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'celebrate' => fn() => $request->session()->get('celebrate'),
            ],
            'app' => [
                'name' => config('app.name'),
                'version' => config('app.version', '1.0.0'),
                'switcher' => config('apps.switcher'),
            ],
            'notifications_count' => fn() => $user
                ? $scopeNotifications($user->unreadNotifications())->count()
                : 0,
            // Unread Inbox (follow-up) messages — drives the "(N)" badge next to
            // the Inbox nav item. Mirrors the inbox's own unread logic.
            'inbox_unread_count' => fn() => $user
                ? \App\Models\FollowUp::where('organization_id', $user->organization_id)
                    ->unreadForViewer($user)
                    ->count()
                : 0,
            'notifications' => fn() => $user
                ? $scopeNotifications($user->notifications())->take(8)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->data['type'] ?? 'info',
                    'title' => $n->data['title'] ?? 'Notification',
                    'message' => $n->data['message'] ?? null,
                    'url' => $n->data['url'] ?? null,
                    'icon' => $n->data['icon'] ?? 'bell',
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at?->toIso8601String(),
                ])
                : [],
        ];
    }
}
