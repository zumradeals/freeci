<?php

namespace App\Modules\Accounts\Exceptions;

use RuntimeException;

/** Compte suspendu : les NOUVELLES activités sont refusées ; les dossiers en cours se poursuivent. Redirige avec un message clair. */
class AccountRestricted extends RuntimeException
{
    public function __construct(string $message = 'Votre compte est suspendu : vous ne pouvez pas démarrer de nouvelle activité. Vos commandes en cours se poursuivent normalement.')
    {
        parent::__construct($message);
    }

    public function render($request)
    {
        return $request->expectsJson() ? response()->json(['message' => $this->getMessage()], 403) : redirect()->back()->with('error', $this->getMessage());
    }
}
