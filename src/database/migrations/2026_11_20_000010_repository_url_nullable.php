<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Form menyimpan string kosong sebagai NULL; URL repo belum terisi saat karya masih draf.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', fn (Blueprint $t) => $t->string('url')->nullable()->change());
    }
};
