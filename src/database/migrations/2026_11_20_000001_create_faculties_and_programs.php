<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('dean_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('study_programs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('faculty_id')->constrained()->cascadeOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete(); // kaprodi
            $t->timestamps();
        });
        Schema::table('users', fn (Blueprint $t) => $t->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete());
        DB::table('users')->where('role', 'pembimbing')->update(['role' => 'dosen']);
    }
};
