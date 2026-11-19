<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NPM diganti Caddy; rute diidentifikasi dari id deployment, bukan id proxy host.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', fn (Blueprint $t) => $t->dropColumn('npm_proxy_host_id'));
    }
};
