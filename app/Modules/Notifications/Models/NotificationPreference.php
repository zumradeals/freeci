<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'email' => 'boolean'];
    }
}
