<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repository extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['validation_report' => 'array', 'validated_at' => 'datetime'];
    }

    public function innovation() { return $this->belongsTo(Innovation::class); }
}
