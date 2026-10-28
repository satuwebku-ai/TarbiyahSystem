<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pendaftaran token push Expo untuk sesi perangkat ini. Token melekat pada baris mobile_tokens milik sesi,
 * jadi keluar dari akun otomatis menghentikan notifikasi di perangkat itu.
 */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^(Expo|Exponent)PushToken\[[A-Za-z0-9_\-]+\]$/'],
            'platform' => ['nullable', Rule::in(['android', 'ios'])],
        ]);

        /** @var MobileToken $current */
        $current = $request->attributes->get('mobile_token');

        // Satu perangkat hanya milik satu sesi: bila berganti akun, sesi lama berhenti menerima push.
        MobileToken::forgetPushToken($data['token'], $current->id);
        $current->forceFill(['push_token' => $data['token'], 'push_platform' => $data['platform'] ?? null])->save();

        return response()->json(['registered' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_token')?->forceFill(['push_token' => null, 'push_platform' => null])->save();

        return response()->json(['registered' => false]);
    }
}
