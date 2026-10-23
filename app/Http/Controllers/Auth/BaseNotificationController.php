<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\WorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kotak masuk notifikasi. Semua query lewat relasi milik pengguna yang login,
 * jadi tidak ada cara membaca atau menandai notifikasi orang lain.
 */
abstract class BaseNotificationController extends Controller
{
    abstract protected function guardName(): string;
    abstract protected function viewName(): string;
    abstract protected function routePrefix(): string;

    public function index(Request $request): View
    {
        $user = $request->user($this->guardName());
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        return view($this->viewName(), [
            'notifications' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'prefix' => $this->routePrefix(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Tandai dibaca lalu arahkan ke halaman terkait; tanpa tautan yang valid, kembali ke daftar. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user($this->guardName())->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $url = WorkflowNotification::urlFor($item->data['route'] ?? null, $item->data['params'] ?? []);

        return $url ? redirect()->to($url) : redirect()->route($this->routePrefix().'notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user($this->guardName())->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
