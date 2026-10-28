<?php

namespace App\Notifications\Channels;

use App\Models\MobileToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Kanal notifikasi push ke aplikasi mobile lewat layanan push Expo.
 * Notifikasi harus punya toExpoPush($notifiable): array (title, body, data, ...).
 *
 * Token yang ditolak Expo karena perangkat sudah tidak terdaftar (DeviceNotRegistered) dilepas dari sesi.
 * Kegagalan jaringan/5xx dilempar ulang supaya antrean mengulang pekerjaan kanal ini saja.
 */
class ExpoPushChannel
{
    public const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpoPush') || ! $notifiable instanceof \Illuminate\Database\Eloquent\Model) {
            return;
        }

        $tokens = MobileToken::pushTokensFor($notifiable);
        if ($tokens === []) {
            return;
        }

        $payload = $notification->toExpoPush($notifiable);
        $messages = array_map(fn (string $to) => $payload + ['to' => $to], $tokens);

        foreach (array_chunk($messages, 100) as $chunk) {
            $request = Http::acceptJson()->asJson()->timeout(10);
            if ($accessToken = config('notifications.expo_access_token')) {
                $request = $request->withToken($accessToken);
            }

            try {
                $response = $request->post(self::ENDPOINT, $chunk);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Layanan push Expo tidak dapat dijangkau: '.$e->getMessage(), 0, $e);
            }

            if ($response->serverError()) {
                throw new RuntimeException('Layanan push Expo menjawab '.$response->status().'.');
            }
            if ($response->failed()) {
                Log::warning('Push Expo ditolak.', ['status' => $response->status(), 'body' => $response->body()]);

                continue;
            }

            foreach ((array) $response->json('data', []) as $i => $ticket) {
                if (($ticket['status'] ?? null) === 'error' && ($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    MobileToken::forgetPushToken($chunk[$i]['to']);
                }
            }
        }
    }
}
