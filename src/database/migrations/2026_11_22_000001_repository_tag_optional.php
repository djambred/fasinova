<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tag opsional: bila kosong, dipakai branch default dan SHA terkininya dikunci saat PIC menyetujui.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', fn (Blueprint $t) => $t->string('tag')->nullable()->default(null)->change());
    }
};
