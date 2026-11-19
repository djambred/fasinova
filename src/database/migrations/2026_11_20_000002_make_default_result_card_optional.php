<?php

use App\Models\Track;
use Illuminate\Database\Migrations\Migration;

// Kartu hasil peminatan tanpa SOP klaim (Full Stack, Robotik/IoT, LLM) tidak wajib diisi; AI dan Cyber Security tetap wajib.
return new class extends Migration {
    public function up(): void
    {
        foreach (Track::whereIn('code', ['fullstack', 'iot', 'llm'])->get() as $t) {
            $t->update(['result_card_schema' => collect($t->result_card_schema ?? [])->map(fn ($f) => array_merge($f, ['required' => false]))->all()]);
        }
    }

    public function down(): void {}
};
