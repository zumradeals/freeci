<?php

namespace App\Modules\Accounts\Security;

use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Double authentification (TOTP) et codes de récupération à usage unique. Le secret est chiffré au repos ; les codes de récupération
 * ne sont conservés que sous forme d'empreinte HMAC et ne sont affichés qu'une fois. Les tentatives sont limitées et journalisées.
 */
final class TwoFactor
{
    public const CODES = 8;

    /** Génère (ou régénère) un secret EN ATTENTE : la double authentification n'est active qu'après confirmation par un code valide. */
    public function begin(User $user): array
    {
        if ($user->hasTwoFactor()) {
            throw new \DomainException('La double authentification est déjà active.');
        }
        $secret = Totp::generateSecret();
        DB::table('users')->where('id', $user->getKey())->update(['two_factor_secret' => Crypt::encryptString($secret), 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null]);

        return ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email, (string) config('freeci.admin.issuer'))];
    }

    /** Secret en attente (pour réafficher l'écran d'activation). */
    public function pending(User $user): ?array
    {
        $raw = DB::table('users')->where('id', $user->getKey())->value('two_factor_secret');
        $confirmed = DB::table('users')->where('id', $user->getKey())->value('two_factor_confirmed_at');
        if ($raw === null || $confirmed !== null) {
            return null;
        }
        $secret = Crypt::decryptString($raw);

        return ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email, (string) config('freeci.admin.issuer'))];
    }

    /** @return list<string>|null les codes de récupération EN CLAIR (affichés une seule fois), ou null si le code est invalide */
    public function confirm(User $user, string $code): ?array
    {
        if ($this->locked($user)) {
            return null;
        }
        $row = DB::table('users')->where('id', $user->getKey())->first(['two_factor_secret', 'two_factor_confirmed_at']);
        if ($row->two_factor_secret === null || $row->two_factor_confirmed_at !== null) {
            return null;
        }
        $step = Totp::verify(Crypt::decryptString($row->two_factor_secret), $code);
        if ($step === null) {
            $this->failed($user, 'mfa_enroll_failed');

            return null;
        }
        RateLimiter::clear($this->key($user));

        return DB::transaction(function () use ($user, $step) {
            // Les connexions mémorisées (cookie « se souvenir de moi ») ne franchiraient pas la deuxième étape : elles sont invalidées.
            DB::table('users')->where('id', $user->getKey())->update(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step, 'remember_token' => Str::random(60)]);
            SecurityLog::record('mfa_enabled', $user->getKey());

            return $this->issueCodes($user);
        });
    }

    /** Code TOTP ou code de récupération. Un code de récupération est consommé. Limité : N échecs → blocage temporaire. */
    public function challenge(User $user, string $code): bool
    {
        if ($this->locked($user)) {
            return false;
        }
        $row = DB::table('users')->where('id', $user->getKey())->first(['two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_step']);
        if ($row->two_factor_secret === null || $row->two_factor_confirmed_at === null) {
            return false;
        }
        $step = Totp::verify(Crypt::decryptString($row->two_factor_secret), $code, $row->two_factor_last_step === null ? null : (int) $row->two_factor_last_step);
        if ($step !== null) {
            // mise à jour conditionnelle : deux requêtes simultanées avec le même code ne passent pas toutes les deux
            $ok = DB::table('users')->where('id', $user->getKey())->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))->update(['two_factor_last_step' => $step]) === 1;
            if ($ok) {
                RateLimiter::clear($this->key($user));
                SecurityLog::record('mfa_passed', $user->getKey(), ['channel' => 'totp']);

                return true;
            }
        } elseif ($this->consumeRecovery($user, $code)) {
            RateLimiter::clear($this->key($user));
            SecurityLog::record('mfa_recovery_used', $user->getKey(), ['remaining' => $this->remaining($user)]);

            return true;
        }
        $this->failed($user, 'mfa_failed');

        return false;
    }

    public function locked(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->key($user), (int) config('freeci.admin.mfa_attempts'));
    }

    public function secondsLocked(User $user): int
    {
        return RateLimiter::availableIn($this->key($user));
    }

    public function remaining(User $user): int
    {
        return DB::table('two_factor_recovery_codes')->where('user_id', $user->getKey())->whereNull('used_at')->count();
    }

    /** Remplace tous les codes (les anciens cessent de fonctionner). @return list<string> */
    public function regenerate(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $codes = $this->issueCodes($user);
            SecurityLog::record('mfa_codes_regenerated', $user->getKey());

            return $codes;
        });
    }

    /** Désactive la double authentification (récupération console, ou changement volontaire). */
    public function reset(User $user, string $by): void
    {
        DB::transaction(function () use ($user, $by) {
            DB::table('users')->where('id', $user->getKey())->update(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null, 'remember_token' => Str::random(60)]);
            DB::table('two_factor_recovery_codes')->where('user_id', $user->getKey())->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();                 // toutes les sessions ouvertes sont fermées
            RateLimiter::clear($this->key($user));
            SecurityLog::record('mfa_reset', $user->getKey(), ['by' => $by]);
        });
    }

    /** Désactivation VOLONTAIRE par la personne (mot de passe et code vérifiés par l'appelant) : les AUTRES sessions sont fermées, la session courante est conservée. */
    public function disable(User $user, string $keepSessionId): void
    {
        DB::transaction(function () use ($user, $keepSessionId) {
            DB::table('users')->where('id', $user->getKey())->update(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null, 'remember_token' => Str::random(60)]);
            DB::table('two_factor_recovery_codes')->where('user_id', $user->getKey())->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->where('id', '<>', $keepSessionId)->delete();
            RateLimiter::clear($this->key($user));
            SecurityLog::record('mfa_disabled', $user->getKey(), ['by' => 'self']);
        });
    }

    /** @return list<string> */
    private function issueCodes(User $user): array
    {
        DB::table('two_factor_recovery_codes')->where('user_id', $user->getKey())->delete();
        $codes = [];
        for ($i = 0; $i < self::CODES; $i++) {
            $plain = strtolower(Str::random(5).'-'.Str::random(5));
            $codes[] = $plain;
            DB::table('two_factor_recovery_codes')->insert(['user_id' => $user->getKey(), 'code_hash' => self::hash($plain), 'created_at' => now()]);
        }

        return $codes;
    }

    private function consumeRecovery(User $user, string $code): bool
    {
        $code = strtolower(trim($code));
        if (! preg_match('/^[a-z0-9]{5}-[a-z0-9]{5}$/', $code)) {
            return false;
        }

        return DB::table('two_factor_recovery_codes')->where('user_id', $user->getKey())->where('code_hash', self::hash($code))->whereNull('used_at')->update(['used_at' => now()]) === 1;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function failed(User $user, string $type): void
    {
        RateLimiter::hit($this->key($user), (int) config('freeci.admin.mfa_lock_minutes') * 60);
        SecurityLog::record($type, $user->getKey());
        if ($this->locked($user)) {
            SecurityLog::record('mfa_locked', $user->getKey());
        }
    }

    private function key(User $user): string
    {
        return 'mfa:'.$user->getKey();
    }
}
