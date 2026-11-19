<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $guarded = [];

    public function innovation() { return $this->belongsTo(Innovation::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
}
