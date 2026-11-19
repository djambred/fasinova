<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['checklist_result' => 'array']; }

    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
}
