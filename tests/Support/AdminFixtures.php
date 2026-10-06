<?php

namespace Tests\Support;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Administrateur « prêt » : habilitation, adresse vérifiée, double authentification activée ; session ayant franchi le défi. */
trait AdminFixtures
{
    protected function readyAdmin(array $attrs = []): User
    {
        $admin = User::factory()->create($attrs + ['email_verified_at' => now()]);
        app(GrantAdministrator::class)($admin, 'test');
        $mfa = app(TwoFactor::class);
        $secret = $mfa->begin($admin)['secret'];
        $mfa->confirm($admin, Totp::code($secret, Totp::step()));

        return $admin->fresh();
    }

    protected function secretOf(User $u): string
    {
        return Crypt::decryptString((string) DB::table('users')->where('id', $u->id)->value('two_factor_secret'));
    }

    /** Session ayant franchi le défi MFA et confirmé récemment l'identité. */
    protected function asAdmin(User $a, bool $recent = true)
    {
        $this->flushSession();                  // chaque appel = une session neuve
        $s = ['admin_mfa' => ['user' => $a->id, 'at' => time()]];
        if ($recent) {
            $s['admin_reauth'] = ['user' => $a->id, 'at' => time()];
        }

        return $this->actingAs($a)->withSession($s);
    }
}
