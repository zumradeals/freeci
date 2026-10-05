<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class ResetPassword
{
    /** @return bool vrai si le mot de passe a été changé (jeton valide, non expiré, non réutilisé) */
    public function __invoke(string $email, string $token, string $password): bool
    {
        $status = Password::reset(
            ['email' => mb_strtolower(trim($email)), 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
