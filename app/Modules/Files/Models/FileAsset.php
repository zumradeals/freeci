<?php

namespace App\Modules\Files\Models;

use App\Modules\Files\Enums\FileState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FileAsset extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['state' => FileState::class, 'size_bytes' => 'integer', 'scanned_at' => 'datetime', 'removed_at' => 'datetime'];
    }
}
