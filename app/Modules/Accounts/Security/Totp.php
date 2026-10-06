<?php

namespace App\Modules\Accounts\Security;

/** TOTP (RFC 6238, SHA-1, 6 chiffres, pas de 30 s), sans dépendance externe. Le secret n'est jamais journalisé. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function code(string $secret, int $step): string
    {
        $key = self::unbase32($secret);
        $hash = hash_hmac('sha1', pack('N2', $step >> 32, $step & 0xFFFFFFFF), $key, true);
        $o = ord($hash[19]) & 0x0F;
        $n = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);

        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function step(?int $at = null): int
    {
        return intdiv($at ?? time(), 30);
    }

    /** @return int|null le pas accepté (tolérance ±1 pas) ; null si le code est faux ou déjà utilisé (pas <= $lastStep) */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $at = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now = self::step($at);
        for ($s = $now - 1; $s <= $now + 1; $s++) {
            if (hash_equals(self::code($secret, $s), $code)) {
                return ($lastStep !== null && $s <= $lastStep) ? null : $s;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    private static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function unbase32(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper($s)) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
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
