<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\User;

/** Crée un compte. Le mot de passe est haché par le modèle (cast « hashed »). */
final class RegisterUser
{
    public function __invoke(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => trim($name),
            'email' => mb_strtolower(trim($email)),
            'password' => $password,
        ]);
        $user->roles()->create(['role' => AccountRole::CLIENT]);

        return $user;
    }
}
