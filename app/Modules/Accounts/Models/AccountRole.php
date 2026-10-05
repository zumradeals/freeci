<?php

namespace App\Modules\Accounts\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AccountRole extends Model
{
    use HasUuids;

    public const CLIENT = 'client';

    public const FREELANCE = 'freelance';

    public $timestamps = false;

    protected $guarded = [];
}
