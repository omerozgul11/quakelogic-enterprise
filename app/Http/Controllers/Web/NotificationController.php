<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()->paginate(30);
        $notifications->getCollection()->transform(fn ($n) => $this->present($n));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        $latest = $user->unreadNotifications()->take(5)->get()->map(fn ($n) => [
            'id' => $n->id,
            'title' => $n->data['title'] ?? 'Notification',
            'message' => $n->data['message'] ?? null,
            'url' => $n->data['url'] ?? null,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'count' => $user->unreadNotifications()->count(),
            'latest' => $latest,
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;
        if ($url && $request->boolean('follow')) {
            return redirect($url);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back();
    }

    private function present(object $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'info',
            'title' => $n->data['title'] ?? 'Notification',
            'message' => $n->data['message'] ?? null,
            'url' => $n->data['url'] ?? null,
            'icon' => $n->data['icon'] ?? 'bell',
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
