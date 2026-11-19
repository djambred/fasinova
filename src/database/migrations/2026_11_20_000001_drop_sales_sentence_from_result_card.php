<?php

use App\Models\ResultCard;
use App\Models\Track;
use Illuminate\Database\Migrations\Migration;

// Mode sales dibatalkan: kolom "kalimat_sales" dibuang dari skema kartu hasil semua track dan dari isi kartu yang sudah ada.
return new class extends Migration {
    public function up(): void
    {
        foreach (Track::all() as $t) {
            $t->update(['result_card_schema' => collect($t->result_card_schema ?? [])->reject(fn ($f) => ($f['key'] ?? null) === 'kalimat_sales')->values()->all()]);
        }
        foreach (ResultCard::all() as $c) {
            $data = $c->data ?? [];
            if (array_key_exists('kalimat_sales', $data)) { unset($data['kalimat_sales']); $c->update(['data' => $data]); }
        }
    }

    public function down(): void {}
};
