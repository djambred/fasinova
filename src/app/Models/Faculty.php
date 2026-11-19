<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faculty extends Model
{
    protected $guarded = [];

    public function dean() { return $this->belongsTo(User::class, 'dean_id'); }
    public function programs() { return $this->hasMany(StudyProgram::class); }
}
