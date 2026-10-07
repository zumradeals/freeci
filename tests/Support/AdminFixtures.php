<?php

namespace Tests\Support;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\Totp;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Admin\Settings\SettingDefinitions;
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

    /** Valeurs actuelles d'un groupe de paramètres, telles que le formulaire les envoie (tous les champs), avec des remplacements. */
    protected function settingsGroup(string $group, array $override = []): array
    {
        $v = [];
        foreach (SettingDefinitions::groups()[$group]['keys'] as $key) {
            $value = config(SettingDefinitions::all()[$key]['path']);
            $v[str_replace('.', '_', $key)] = is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '');
        }

        return array_merge($v, $override);
    }
}
