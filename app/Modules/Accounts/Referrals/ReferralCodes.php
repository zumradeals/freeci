<?php

namespace App\Modules\Accounts\Referrals;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Code de parrainage d'un compte (F-14) : 8 caractères non devinables, sans lettres ambiguës, créé au premier besoin et jamais modifié. */
final class ReferralCodes
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function forUser(string $userId): string
    {
        $c = DB::table('referral_codes')->where('user_id', $userId)->value('code');
        if ($c !== null) {
            return (string) $c;
        }
        for ($i = 0; $i < 10; $i++) {
            $code = '';
            for ($k = 0; $k < 8; $k++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            try {
                DB::table('referral_codes')->insert(['id' => (string) Str::uuid(), 'user_id' => $userId, 'code' => $code, 'created_at' => now()]);

                return $code;
            } catch (UniqueConstraintViolationException) {
                $existing = DB::table('referral_codes')->where('user_id', $userId)->value('code');   // concurrence : un autre appel l'a créé
                if ($existing !== null) {
                    return (string) $existing;
                }
            }
        }
        throw new \RuntimeException('Impossible de créer un code de parrainage.');
    }

    /** Forme normalisée (majuscules, sans espace ni tiret) ou null si le format est invalide. */
    public static function normalize(?string $input): ?string
    {
        $c = strtoupper((string) preg_replace('/[\s-]+/', '', (string) $input));

        return preg_match('/^['.self::ALPHABET.']{8}$/', $c) ? $c : null;
    }

    /** Propriétaire du code, si son compte peut parrainer (non suspendu). */
    public function ownerOf(?string $input): ?object
    {
        $code = self::normalize($input);
        if ($code === null) {
            return null;
        }

        return DB::table('referral_codes as r')->join('users as u', 'u.id', '=', 'r.user_id')->where('r.code', $code)->whereNull('u.suspended_at')->first(['r.user_id', 'r.code', 'u.name', 'u.email']);
    }

    /** « Koffi A. » : prénom et initiale seulement. */
    public static function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return 'Un membre';
        }

        return count($parts) === 1 ? $parts[0] : $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';
    }
}
