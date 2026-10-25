<?php

namespace App\Services;

/**
 * TOTP (RFC 6238, SHA-1, 6 digit, 30 detik) — kompatibel dengan Google Authenticator, Microsoft Authenticator,
 * Authy, 1Password, dst. Tanpa paket tambahan.
 */
class TwoFactorAuthenticator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** Toleransi selisih jam: ±1 langkah (30 detik) di sekitar waktu server. */
    private const WINDOW = 1;

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function provisioningUri(string $issuer, string $account, string $secret): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function codeAt(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0, $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function currentStep(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    /**
     * Periksa kode. Mengembalikan nomor langkah yang cocok, atau null bila salah.
     * Langkah yang sama atau lebih lama dari $lastUsedStep ditolak agar satu kode tidak bisa dipakai dua kali.
     */
    public function verify(string $secret, string $code, ?int $lastUsedStep = null, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $current = $this->currentStep($timestamp);
        $matched = null;
        // Semua kandidat diperiksa (tanpa berhenti di yang pertama cocok) agar waktu respons tetap.
        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
            if (hash_equals($this->codeAt($secret, $step), $code) && ($lastUsedStep === null || $step > $lastUsedStep)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    /** @return list<string> kode pemulihan polos, format xxxxx-xxxxx */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtolower($this->base32Encode(random_bytes(7))); // 56 bit -> 12 karakter
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5, 5);
        }

        return $codes;
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', $this->normalizeRecoveryCode($code), (string) config('app.key'));
    }

    public function normalizeRecoveryCode(string $code): string
    {
        return strtolower(preg_replace('/[\s-]+/', '', $code) ?? '');
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
