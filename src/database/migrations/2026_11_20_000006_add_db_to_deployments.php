<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', function (Blueprint $t) {
            $t->string('db_engine')->nullable();   // null | postgres | mariadb
            $t->string('db_name')->nullable();
            $t->string('db_user')->nullable();
            $t->text('db_password')->nullable();   // terenkripsi (cast)
        });
    }
};
