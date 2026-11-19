<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('innovations', function (Blueprint $t) {
            $t->string('form_type')->nullable();          // web | mobile | iot | model | other
            $t->string('journal_url')->nullable();        // tautan jurnal bila sudah terpublikasi
            $t->boolean('tos_accepted')->default(false);  // TOS persetujuan publikasi dicentang
            $t->timestamp('tos_accepted_at')->nullable();
        });
        // karya yang sudah ada dibuat dengan alur web/deploy
        DB::table('innovations')->update(['form_type' => 'web', 'tos_accepted' => DB::raw('consent_ok'), 'tos_accepted_at' => DB::raw('CASE WHEN consent_ok THEN CURRENT_TIMESTAMP ELSE NULL END')]);
    }
};
