<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Queries\OperationsStatus;
use Illuminate\View\View;

/** État de l'exploitation et liste de préparation à l'ouverture (lecture seule, informative : n'active ni ne bloque rien). */
class OperationsController extends Controller
{
    public function __invoke(OperationsStatus $status): View
    {
        return view('admin.operations', ['d' => $status()]);
    }
}
