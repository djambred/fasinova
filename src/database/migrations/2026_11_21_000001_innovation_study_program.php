<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Prodi dipilih per karya (menentukan kaprodi/dekan sebagai PIC); karya lama ikut prodi ketuanya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('innovations', fn (Blueprint $t) => $t->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete());
        // subquery (bukan UPDATE ... FROM) agar jalan di Postgres dan SQLite
        DB::statement('UPDATE innovations SET study_program_id = (SELECT users.study_program_id FROM users WHERE users.id = innovations.owner_id)');
    }

    public function down(): void
    {
        Schema::table('innovations', fn (Blueprint $t) => $t->dropConstrainedForeignId('study_program_id'));
    }
};
