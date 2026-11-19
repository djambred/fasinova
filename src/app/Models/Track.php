<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required_files' => 'array', 'checklist' => 'array', 'result_card_schema' => 'array',
            'forbidden_images' => 'array', 'deployable' => 'boolean',
        ];
    }

    public function pics() { return $this->belongsToMany(User::class, 'track_pics'); }
}
