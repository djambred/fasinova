<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Cloudflare Turnstile untuk form publik (kolaborasi, pendaftaran). */
class Turnstile
{
    public static function verify(Request $r): void
    {
        // shortcut: tanpa kunci Turnstile (dev) pemeriksaan dilewati; wajib diisi di produksi.
        if (! $secret = config('fasinova.turnstile_secret')) return;
        $ok = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret, 'response' => $r->input('cf-turnstile-response'), 'remoteip' => $r->ip(),
        ])->json('success');
        abort_unless($ok, 422, 'Verifikasi Turnstile gagal.');
    }
}
