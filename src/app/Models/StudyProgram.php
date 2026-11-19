<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyProgram extends Model
{
    protected $guarded = [];

    public function faculty() { return $this->belongsTo(Faculty::class); }
    public function head() { return $this->belongsTo(User::class, 'head_id'); }
    public function users() { return $this->hasMany(User::class); }
}
