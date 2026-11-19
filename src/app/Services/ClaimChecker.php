<?php

namespace App\Services;

class ClaimChecker
{
    /** @return list<string> peringatan untuk PIC (PRD bagian 4). */
    public static function warnings(array $data, string $trackCode): array
    {
        $w = [];
        $text = collect($data)->flatten()->filter(fn ($v) => is_string($v))->implode("\n");
        foreach (config('fasinova.claim_phrases') as $p) {
            if (preg_match('/\b'.preg_quote($p, '/').'\b/iu', $text)) $w[] = "Klaim berlebihan: \"$p\".";
        }
        // persentase tanpa nama metrik di dekatnya (8 karakter sebelum angka)
        if (preg_match_all('/(.{0,12})\d+([.,]\d+)?\s*%/u', $text, $m)) {
            foreach ($m[1] as $ctx) {
                if (! preg_match('/f1|recall|akurasi|accuracy|precision|presisi/iu', $ctx.'')) { $w[] = 'Persentase tanpa nama metrik.'; break; }
            }
        }
        if ($trackCode === 'ai' && empty($data['baseline'])) $w[] = 'Kartu AI wajib memuat baris baseline.';
        return array_values(array_unique($w));
    }

    /** Kartu AI tanpa baseline ditolak (bukan hanya peringatan). */
    public static function blocking(array $data, string $trackCode): ?string
    {
        return $trackCode === 'ai' && empty(trim((string) ($data['baseline'] ?? ''))) ? 'Kartu hasil AI wajib memuat baris baseline.' : null;
    }
}
