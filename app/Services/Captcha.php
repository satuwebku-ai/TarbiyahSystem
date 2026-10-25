<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * CAPTCHA untuk form publik (login, lupa kata sandi, pendaftaran). Pengaturan di config/captcha.php
 * dan menu Pengaturan Aplikasi > CAPTCHA.
 *
 * Pemakaian: di controller panggil verify($request, 'login'); di Blade @include('partials.captcha', ['page' => 'login']).
 */
class Captcha
{
    public const PAGES = ['login', 'forgot', 'register'];
    private const SESSION_KEY = 'captcha.math';

    public function provider(): string
    {
        $provider = (string) config('captcha.provider', 'off');

        return $provider === 'math' || isset(config('captcha.providers')[$provider]) ? $provider : 'off';
    }

    /** Provider eksternal dipilih tetapi site key / secret key belum lengkap. */
    public function isMisconfigured(): bool
    {
        return $this->isExternal() && (! filled(config('captcha.site_key')) || ! filled(config('captcha.secret_key')));
    }

    public function isExternal(): bool
    {
        return isset(config('captcha.providers')[$this->provider()]);
    }

    /** CAPTCHA berlaku di halaman ini (provider aktif, kunci lengkap, dan halamannya dicentang). */
    public function enabledFor(string $page): bool
    {
        return $this->provider() !== 'off'
            && ! $this->isMisconfigured()
            && (bool) config("captcha.pages.{$page}", false);
    }

    /** Konfigurasi provider eksternal aktif (script, field, class widget), atau null. */
    public function externalConfig(): ?array
    {
        return $this->isExternal() ? config('captcha.providers.'.$this->provider()) : null;
    }

    /**
     * Host tambahan untuk CSP, per jenis (script|frame|connect|style). Hanya terisi bila provider eksternal
     * aktif dan kuncinya lengkap, jadi tanpa CAPTCHA kebijakan CSP tetap seketat semula.
     *
     * @return list<string>
     */
    public function cspSources(string $type): array
    {
        if (! $this->isExternal() || $this->isMisconfigured()) {
            return [];
        }

        return (array) ($this->externalConfig()['csp'][$type] ?? []);
    }

    /** Lempar ValidationException (field "captcha") bila jawaban salah. Tidak melakukan apa pun bila CAPTCHA tidak berlaku. */
    public function verify(Request $request, string $page): void
    {
        if (! $this->enabledFor($page)) {
            return;
        }

        $error = $this->isExternal() ? $this->verifyExternal($request) : $this->verifyMath($request);
        if ($error !== null) {
            throw ValidationException::withMessages(['captcha' => $error]);
        }
    }

    // ---- math (bawaan) -------------------------------------------------------

    /** Buat pertanyaan baru dan simpan jawabannya (hash) di sesi. @return array{token:string, question:string} */
    public function newMathChallenge(): array
    {
        $a = random_int(2, 12);
        $b = random_int(2, 9);
        [$question, $answer] = match (random_int(0, 2)) {
            0 => ["{$a} + {$b}", $a + $b],
            1 => [($a + $b)." − {$b}", $a],
            default => ["{$a} × {$b}", $a * $b],
        };

        $token = Str::random(24);
        $store = $this->pruneMath((array) session()->get(self::SESSION_KEY, []));
        $store[$token] = ['h' => $this->hashAnswer($token, (string) $answer), 'exp' => now()->addMinutes((int) config('captcha.math_ttl_minutes', 10))->timestamp];
        // Simpan paling banyak 6 pertanyaan (beberapa tab terbuka); yang tertua dibuang.
        session()->put(self::SESSION_KEY, array_slice($store, -6, null, true));

        return ['token' => $token, 'question' => $question];
    }

    private function verifyMath(Request $request): ?string
    {
        $token = (string) $request->input('captcha_token', '');
        $answer = preg_replace('/\D+/', '', (string) $request->input('captcha_answer', '')) ?? '';

        $store = $this->pruneMath((array) $request->session()->get(self::SESSION_KEY, []));
        $entry = $store[$token] ?? null;
        // Satu pertanyaan hanya boleh dicoba sekali: dibuang apa pun hasilnya.
        unset($store[$token]);
        $request->session()->put(self::SESSION_KEY, $store);

        if ($entry === null || $answer === '') {
            return 'Jawab pertanyaan CAPTCHA dengan benar.';
        }

        return hash_equals($entry['h'], $this->hashAnswer($token, $answer)) ? null : 'Jawaban CAPTCHA salah. Coba pertanyaan yang baru.';
    }

    private function pruneMath(array $store): array
    {
        return array_filter($store, fn ($e) => is_array($e) && ($e['exp'] ?? 0) > now()->timestamp);
    }

    private function hashAnswer(string $token, string $answer): string
    {
        return hash_hmac('sha256', $token.'|'.$answer, (string) config('app.key'));
    }

    // ---- provider eksternal --------------------------------------------------

    private function verifyExternal(Request $request): ?string
    {
        $provider = $this->externalConfig();
        $response = trim((string) $request->input($provider['field'], ''));
        if ($response === '') {
            return 'Selesaikan verifikasi CAPTCHA terlebih dahulu.';
        }

        try {
            $result = Http::asForm()
                ->timeout((int) config('captcha.timeout', 5))
                ->post($provider['verify'], [
                    'secret' => (string) config('captcha.secret_key'),
                    'response' => $response,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('CAPTCHA: layanan verifikasi tidak terjangkau.', ['provider' => $this->provider(), 'error' => $e->getMessage()]);

            return 'Layanan CAPTCHA tidak dapat dihubungi. Coba lagi sebentar lagi.';
        } catch (Throwable $e) {
            Log::warning('CAPTCHA: verifikasi gagal.', ['provider' => $this->provider(), 'error' => $e->getMessage()]);

            return 'Verifikasi CAPTCHA gagal. Coba lagi.';
        }

        if ($result->json('success') === true) {
            return null;
        }

        // Kode galat seperti "invalid-input-secret" berarti kunci salah: dicatat agar admin bisa menelusuri.
        Log::warning('CAPTCHA ditolak.', ['provider' => $this->provider(), 'codes' => $result->json('error-codes')]);

        return 'Verifikasi CAPTCHA gagal. Muat ulang halaman lalu coba lagi.';
    }
}
