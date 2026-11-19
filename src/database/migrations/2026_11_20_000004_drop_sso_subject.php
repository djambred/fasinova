<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SSO dibatalkan; login hanya email + kata sandi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['sso_subject']);
            $t->dropColumn('sso_subject');
        });
    }
};
