<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;

final class GetFreelanceProfile
{
    /** @return array{display_name:string,headline:string,city:?string}|null */
    public function __invoke(User $user): ?array
    {
        $p = $user->freelanceProfile;

        return $p === null ? null : ['display_name' => $p->display_name, 'headline' => $p->headline, 'city' => $p->city];
    }
}
