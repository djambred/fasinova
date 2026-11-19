<?php

namespace App\Services;

use App\Models\Deployment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Atur rute `<subdomain>.<domain>` → 127.0.0.1:<port> lewat admin API Caddy.
 * Admin API hanya lewat unix socket (tidak ada port jaringan). Rute ber-ID `fz-<id deployment>`,
 * disisipkan di server `fz-server` (lihat docker/caddy/config.json.tpl).
 */
class CaddyClient
{
    public const SERVER = 'fz-server';

    private function http(): PendingRequest
    {
        return Http::withOptions(['curl' => [CURLOPT_UNIX_SOCKET_PATH => config('fasinova.caddy_socket')]])
            ->baseUrl('http://localhost')->acceptJson()->timeout(10);
    }

    public static function routeId(Deployment $d): string { return 'fz-'.$d->id; }

    public static function route(Deployment $d): array
    {
        return [
            '@id' => self::routeId($d),
            'match' => [['host' => [$d->subdomain.'.'.config('fasinova.domain')]]],
            'handle' => [self::proxyHandler($d)],
            'terminal' => true,
        ];
    }

    /** Aplikasi yang melayani TLS sendiri: sambungkan lewat TLS tanpa verifikasi (sertifikat publik tetap dari Caddy, bukan dari aplikasi). */
    private static function proxyHandler(Deployment $d): array
    {
        $h = ['handler' => 'reverse_proxy', 'upstreams' => [['dial' => '127.0.0.1:'.$d->host_port]]];
        if ($d->upstream_tls) $h['transport'] = ['protocol' => 'http', 'tls' => ['insecure_skip_verify' => true]];
        return $h;
    }

    /** Buat atau perbarui rute (idempoten). */
    public function upsert(Deployment $d): void
    {
        $this->delete($d);
        // Rute karya di LEVEL ATAS server (index 0, sebelum catch-all 404): Caddy hanya mengelola sertifikat
        // otomatis untuk host matcher di rute level atas, bukan yang bersarang di subroute.
        $this->http()->put('/id/'.self::SERVER.'/routes/0', self::route($d))->throw();
    }

    public function delete(Deployment $d): void
    {
        $r = $this->http()->delete('/id/'.self::routeId($d));
        if (! $r->successful() && $r->status() !== 404 && ! str_contains($r->body(), 'unknown object ID')) $r->throw();
    }

    /**
     * Saat karya dihapus: ganti rute dengan jawaban 404 untuk host itu (ID sama, jadi deploy ulang otomatis menimpanya).
     * Host tetap terdaftar di rute level atas sehingga sertifikatnya tetap ada dan pengunjung melihat 404, bukan galat TLS.
     */
    public function tombstone(Deployment $d): void
    {
        $this->delete($d);
        $this->http()->put('/id/'.self::SERVER.'/routes/0', [
            '@id' => self::routeId($d),
            'match' => [['host' => [$d->subdomain.'.'.config('fasinova.domain')]]],
            'handle' => [['handler' => 'static_response', 'status_code' => 404, 'body' => 'Karya tidak ditemukan atau sudah dihapus']],
            'terminal' => true,
        ])->throw();
    }

    /** Pasang ulang rute yang hilang/berubah (mis. Caddy restart). @return bool true bila ada perubahan */
    public function ensure(Deployment $d): bool
    {
        $cur = $this->http()->get('/id/'.self::routeId($d));
        if ($cur->successful() && ($cur->json('handle.0.upstreams.0.dial') === '127.0.0.1:'.$d->host_port)
            && ($cur->json('match.0.host.0') === $d->subdomain.'.'.config('fasinova.domain'))
            && ((bool) $cur->json('handle.0.transport.tls.insecure_skip_verify') === (bool) $d->upstream_tls)) {
            return false;
        }
        $this->upsert($d);
        return true;
    }
}
