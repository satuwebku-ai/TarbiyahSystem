<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kotak masuk notifikasi (tabel notifications bawaan Laravel, sama dengan portal web).
 * Semua query lewat relasi milik akun pada token, jadi tidak bisa menyentuh notifikasi orang lain.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->notifications()->latest()->limit(50)->get()->map(fn ($n) => [
                'id' => $n->id,
                'kind' => $n->data['kind'] ?? null,
                'title' => $n->data['title'] ?? 'Notifikasi',
                'body' => $n->data['body'] ?? null,
                'icon' => $n->data['icon'] ?? null,
                'readAt' => $n->read_at?->toIso8601String(),
                'createdAt' => $n->created_at?->toIso8601String(),
            ])->values()
        );
    }

    /** Tanpa "ids": tandai semua dibaca. Dengan "ids": hanya yang disebut (milik akun ini). */
    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['string', 'max:64'],
        ]);

        $query = $request->user()->unreadNotifications();
        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        return response()->json(['updated' => $query->update(['read_at' => now()])]);
    }
}
