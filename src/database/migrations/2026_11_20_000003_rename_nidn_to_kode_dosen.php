<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->renameColumn('nidn', 'kode_dosen'));
        Schema::table('users', fn (Blueprint $t) => $t->unique('kode_dosen'));
    }
};
