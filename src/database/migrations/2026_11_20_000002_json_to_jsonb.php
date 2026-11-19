<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Postgres: tipe json tidak punya operator "=", sehingga SELECT DISTINCT (mis. Select relasi many-to-many Filament) gagal.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        foreach ([
            'tracks' => ['required_files', 'checklist', 'result_card_schema', 'forbidden_images'],
            'innovations' => ['materials', 'members'],
            'repositories' => ['validation_report'], 'deployments' => ['allowed_caps'],
            'result_cards' => ['data', 'claim_warnings'], 'reviews' => ['checklist_result'], 'audit_logs' => ['meta'],
        ] as $table => $cols) {
            foreach ($cols as $c) DB::statement("ALTER TABLE $table ALTER COLUMN $c TYPE jsonb USING $c::jsonb");
        }
    }
};
