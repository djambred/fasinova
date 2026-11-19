<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvVariable extends Model
{
    protected $guarded = [];
    protected $hidden = ['value']; // tidak pernah ikut ke respons/serialisasi

    protected function casts(): array
    {
        return ['value' => 'encrypted', 'is_secret' => 'boolean'];
    }
}
