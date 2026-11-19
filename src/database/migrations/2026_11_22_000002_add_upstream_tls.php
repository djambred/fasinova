<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Aplikasi yang menangani TLS sendiri (port 80 hanya mengalihkan ke https): Caddy meneruskan ke port 443 lewat TLS.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployments', fn (Blueprint $t) => $t->boolean('upstream_tls')->default(false));
    }
};
