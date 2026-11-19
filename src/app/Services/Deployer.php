<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\DeployHistory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Support\Notify;
use Symfony\Component\Process\Process;

/** Alur deploy PRD 6.2. Berjalan di worker `deployer` terhadap Docker rootless user runner. */
class Deployer
{
    private Deployment $d;
    private array $secrets = [];

    public function __construct(private CaddyClient $proxy = new CaddyClient) {}

    public function deploy(Deployment $d): void
    {
        $this->d = $d->load('innovation.track', 'innovation.repository', 'innovation.envVariables');
        $started = now();
        $upStarted = false;
        $i = $this->d->innovation;
        $repo = $i->repository;
        $this->secrets = $i->envVariables->where('is_secret', true)->pluck('value')->filter(fn ($v) => strlen($v) >= 4)->all();
        $this->d->update(['status' => 'queued', 'started_at' => $started, 'finished_at' => null, 'commit_sha' => $repo->locked_commit_sha, 'upstream_tls' => false]);

        try {
            $sha = $repo->locked_commit_sha ?: throw new \RuntimeException('SHA belum dikunci (karya belum disetujui).');
            $dir = $this->d->dir();

            $this->step('cloning');
            // Pengecekan 'tag dipindah' hanya untuk tag sungguhan; untuk branch/commit/tanpa tag, SHA yang dikunci saat persetujuan sudah menjaga integritas.
            if (filled($repo->tag) && ($repo->validation_report['ref_type'] ?? 'tag') === 'tag') $this->assertTagUnchanged(self::cloneUrl($repo->url), $repo->tag, $sha);
            $this->downQuietly('cloning', 'stack lama dihentikan sebelum deploy ulang'); // jangan hapus folder di bawah container yang masih jalan
            $this->removeDir($dir);
            File::ensureDirectoryExists(dirname($dir));
            $this->run(['git', 'clone', '--quiet', self::cloneUrl($repo->url), $dir], null, 'cloning', 300);
            $this->run(['git', 'checkout', '--quiet', '--detach', $sha], $dir, 'cloning');
            $head = trim($this->run(['git', 'rev-parse', 'HEAD'], $dir, 'cloning'));
            if ($head !== $sha) throw new \RuntimeException("HEAD $head bukan SHA terkunci $sha.");
            // Symlink dari repo yang menunjuk ke luar folder karya = jalan membaca/menulis berkas karya lain atau host.
            foreach (self::stripEscapingSymlinks($dir) as $rel) $this->log('cloning', 'err', "symlink keluar folder karya dihapus: $rel");

            $this->step('scanning');
            // Pemindaian rahasia bersifat penasihat: repo publik yang berisi kunci tidak membahayakan server, hanya pemiliknya.
            // Deploy dilanjutkan (env dari FASINOVA menimpa .env di repo); PIC dan tim diberi peringatan.
            $leak = new Process(['gitleaks', 'detect', '--source', $dir, '--no-banner', '--redact', '--exit-code', '1']);
            $leak->setTimeout(300)->run();
            if ($leak->getExitCode() === 1) {
                $out = $this->mask($leak->getOutput().$leak->getErrorOutput());
                if (config('fasinova.gitleaks_block')) throw new \RuntimeException("gitleaks menemukan rahasia di repo.\n$out");
                $this->log('scanning', 'err', 'PERINGATAN: gitleaks menemukan rahasia di repo publik (nilai tersamar). Deploy dilanjutkan.');
                foreach (array_slice(preg_split('/\r?\n/', trim($out)), 0, 30) as $line) if ($line !== '') $this->log('scanning', 'err', $line);
                $this->notify('Rahasia terdeteksi di repo publik', 'Deploy tetap dilanjutkan. Disarankan merotasi kunci yang pernah ter-commit.', 'warning');
            } elseif ($leak->getExitCode() > 1) {
                $this->log('scanning', 'err', 'PERINGATAN: gitleaks gagal dijalankan; pemindaian dilewati: '.trim($leak->getErrorOutput()));
            }

            $dbEnv = [];
            if ($this->d->db_engine) {
                $this->log('scanning', 'out', 'Menyiapkan database '.$this->d->db_engine);
                $dbEnv = (new DbProvisioner)->provision($this->d);
                $this->secrets[] = $dbEnv['DB_PASSWORD'];
            }
            $publicUrl = 'https://'.$this->d->subdomain.'.'.config('fasinova.domain');
            $envMap = self::envMap($i->envVariables, $dbEnv, $publicUrl);
            $envText = self::envText($envMap);
            self::writeFile("$dir/.env", $envText, 0600);
            $json = $this->run(['docker', 'compose', '-p', $this->d->project(), '--project-directory', $dir, 'config', '--format', 'json'], $dir, 'scanning');
            $compose = self::decodeCompose($json) ?: throw new \RuntimeException('docker compose config tidak menghasilkan JSON.');
            $viol = ComposePolicy::check($compose, $i->track->forbidden_images ?? [], $dir);
            if ($viol) throw new \RuntimeException("Kebijakan compose dilanggar:\n- ".implode("\n- ", $viol));
            // env juga ke folder aplikasi yang benar-benar di-mount/di-build (mis. src/.env untuk Laravel); menimpa .env ter-commit
            foreach ($this->nestedEnvFiles($dir, $compose) as $path) {
                self::writeFile($path, $envText, 0644); // dibaca user non-root di container (rootless: bukan pemilik file)
                $this->log('scanning', 'out', 'env ditulis ke '.ltrim(substr($path, strlen($dir)), '/'));
            }
            if ($i->track->code === 'cs' && ! $i->safe_service_ok) throw new \RuntimeException('Cyber Security: PIC belum menandai service aman.');

            $port = $this->d->host_port ?: $this->allocatePort();
            [$public, $internal] = self::detectPublic($compose, $this->d->public_service, $this->d->internal_port ?: ($i->track->default_port ?: 8501));
            $this->log('scanning', 'out', "service publik: $public (port $internal)");
            $this->d->update(['public_service' => $public, 'internal_port' => $internal]); // dipakai health check
            $final = ComposePolicy::harden($compose, $i->slug, $public, $internal, $port,
                (float) $this->d->cpu_limit, $this->d->mem_limit_mb, $this->d->allowed_caps ?? [], (bool) $this->d->db_engine, $envMap);
            self::writeFile("$dir/fasinova.compose.json", json_encode($final, JSON_UNESCAPED_SLASHES), 0600);   // berisi nilai env

            $this->step('building');
            $upStarted = true;
            $this->run($this->compose('up', '-d', '--build'), $dir, 'building', config('fasinova.build_timeout'));

            $this->step('starting');
            $this->step('health_check');
            if (! $this->waitHealthy()) throw new \RuntimeException('Health check gagal (aplikasi tidak menjawab sampai batas waktu atau service berhenti); lihat log service di atas.');

            // Aplikasi yang menangani TLS sendiri (port 80 hanya `return 301 https://...`, port 443 melayani aplikasi): teruskan ke 443 lewat TLS.
            [$code, $redirect] = self::probe($this->d);
            if (self::redirectsToHttps($code, $redirect) && in_array(443, self::declaredTargets($compose, $public))) {
                $this->log('health_check', 'out', "port $internal hanya mengalihkan ke https ($redirect); beralih ke port 443 dengan TLS");
                $this->d->update(['upstream_tls' => true, 'internal_port' => 443]);
                $final = ComposePolicy::harden($compose, $i->slug, $public, 443, $port,
                    (float) $this->d->cpu_limit, $this->d->mem_limit_mb, $this->d->allowed_caps ?? [], (bool) $this->d->db_engine, $envMap);
                self::writeFile("$dir/fasinova.compose.json", json_encode($final, JSON_UNESCAPED_SLASHES), 0600);
                $this->run($this->compose('up', '-d'), $dir, 'starting', 300);   // hanya memetakan ulang port; image sudah ada
                if (! $this->waitHealthy()) throw new \RuntimeException('Health check gagal setelah beralih ke port 443 (TLS).');
            }

            $this->proxy->upsert($this->d->refresh());
            $url = 'https://'.$this->d->subdomain.'.'.config('fasinova.domain');
            $this->d->update(['status' => 'live', 'url' => $url, 'needs_redeploy' => false, 'health_failures' => 0, 'finished_at' => now()]);
            $this->history($started, 'live');
            $this->notify('Demo live', $url, 'success');
        } catch (\Throwable $e) {
            $this->log('failed', 'err', $this->mask($e->getMessage()));
            if ($upStarted) $this->downQuietly(); // jangan biarkan container setengah jadi terus memakan RAM (data/volume dipertahankan)
            $this->d->update(['status' => 'failed', 'finished_at' => now()]);
            $this->history($started, 'failed');
            $this->notify('Deploy gagal', 'Lihat log di menu Deploy.', 'danger');
        }
    }

    public function stop(Deployment $d, string $to = 'stopped'): void
    {
        $this->d = $d;
        $this->run($this->compose('stop'), $d->dir(), 'stop');
        $d->update(['status' => $to]);
    }

    public function start(Deployment $d): void
    {
        $this->d = $d;
        $this->run($this->compose('start'), $d->dir(), 'start');
        $d->update(['status' => 'live', 'health_failures' => 0]);
    }

    public function restart(Deployment $d): void
    {
        $this->d = $d;
        $this->run($this->compose('restart'), $d->dir(), 'restart');
    }

    public function remove(Deployment $d): void
    {
        $this->d = $d;
        if (File::exists($d->dir().'/fasinova.compose.json')) {
            $this->run($this->compose('down', '-v', '--remove-orphans'), $d->dir(), 'remove');
        }
        if ($d->url) $this->proxy->tombstone($d); // hanya bila pernah live; host tetap menjawab 404 dengan sertifikat yang sama
        $this->removeDir($d->dir());
        (new DbProvisioner)->drop($d); // data DB karya ikut dihapus saat stack dihapus
        $d->update(['status' => 'not_deployed', 'host_port' => null, 'url' => null]);
    }

    /**
     * Health check dari DALAM jaringan karya lewat container curl sekali pakai di Docker rootless.
     * (Container `deployer` ada di Docker utama dan tidak bisa menjangkau loopback host; jalur ini juga tidak
     * bergantung pada port host.) Bila docker gagal dijalankan, jatuh ke cek HTTP ke host.
     */
    public static function healthy(Deployment $d): bool
    {
        [$code] = self::probe($d);
        if ($code !== null) return $code >= 200 && $code < 400;
        try { // docker tak bisa dijalankan: cek HTTP ke host (hanya jalan bila host terjangkau dari container ini)
            return Http::timeout(5)->get('http://'.config('fasinova.health_host', '127.0.0.1').":{$d->host_port}{$d->health_path}")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Satu permintaan dari DALAM jaringan karya lewat container curl sekali pakai di Docker rootless.
     * @return array{0:?int,1:string} [kode HTTP, URL redirect]; kode null bila probe tak bisa dijalankan atau tak ada respons
     */
    public static function probe(Deployment $d): array
    {
        if (! $d->public_service || ! $d->internal_port) return [null, ''];
        $scheme = $d->upstream_tls ? 'https' : 'http';
        $p = new Process(['docker', 'run', '--rm', '--network', $d->project().'_default', '--memory', '32m', '--pids-limit', '32',
            config('fasinova.probe_image'), '-s', '-k', '-o', '/dev/null', '--max-time', '5', '-w', '%{http_code} %{redirect_url}',
            "$scheme://{$d->public_service}:{$d->internal_port}{$d->health_path}"]);
        $p->setTimeout(90)->run();
        if (in_array($p->getExitCode(), [125, 126, 127])) return [null, ''];       // docker/jaringan bermasalah
        [$code, $redirect] = array_pad(explode(' ', trim($p->getOutput()), 2), 2, '');
        return [(int) $code ?: 0, $redirect];                                         // 0 = tidak ada respons (curl gagal)
    }

    /** Respons 3xx ke alamat https = aplikasi menangani TLS sendiri. */
    public static function redirectsToHttps(?int $code, string $redirect): bool
    {
        return in_array($code, [301, 302, 307, 308], true) && str_starts_with(strtolower($redirect), 'https://');
    }

    /** Port tujuan yang dideklarasikan service di `ports:` (bentuk panjang hasil `compose config`, atau string). */
    public static function declaredTargets(array $compose, string $service): array
    {
        $t = [];
        foreach ((array) ($compose['services'][$service]['ports'] ?? []) as $p) {
            $t[] = (int) (is_array($p) ? ($p['target'] ?? 0) : array_slice(explode(':', preg_replace('#/.*$#', '', (string) $p)), -1)[0]);
        }
        return array_values(array_filter($t));
    }

    /**
     * Tunggu sampai sehat. Gagal cepat bila service publik sudah berhenti atau crash-loop (tak ada gunanya menunggu 5 menit),
     * dan sertakan potongan lognya supaya penyebabnya terlihat di log deploy.
     */
    private function waitHealthy(): bool
    {
        $max = (int) config('fasinova.health_attempts', 50);
        $restarting = 0;
        for ($n = 1; $n <= $max; $n++) {
            if (self::healthy($this->d)) return true;
            $state = self::parseComposePs($this->psOutput())[$this->d->public_service] ?? 'tidak-ada';
            if ($n === 1 || $n % 5 === 0) $this->log('health_check', 'out', "percobaan $n/$max belum sehat (service {$this->d->public_service}: $state)");
            if (in_array($state, ['exited', 'dead'], true)) { $this->log('health_check', 'err', "service publik berhenti ($state)"); $this->logTail(); return false; }
            $restarting = $state === 'restarting' ? $restarting + 1 : 0;
            if ($restarting >= 5) { $this->log('health_check', 'err', 'service publik crash berulang (restarting)'); $this->logTail(); return false; }
            sleep(6);
        }
        $this->logTail();
        return false;
    }

    /** @return array<string,string> service => state (running, exited, restarting, ...) dari output `compose ps -a --format json` */
    public static function parseComposePs(string $out): array
    {
        $rows = json_decode($out, true);                               // bentuk array JSON
        if (! is_array($rows) || ($rows && ! array_is_list($rows))) {   // atau satu objek per baris (NDJSON)
            $rows = array_filter(array_map(fn ($l) => json_decode($l, true), preg_split('/\r?\n/', trim($out))));
        }
        $states = [];
        foreach ($rows as $r) if (isset($r['Service'], $r['State'])) $states[$r['Service']] = strtolower($r['State']);
        return $states;
    }

    private function psOutput(): string
    {
        $p = new Process($this->compose('ps', '-a', '--format', 'json'), $this->d->dir());
        $p->setTimeout(60)->run();
        return $p->isSuccessful() ? $p->getOutput() : '';
    }

    /** Potongan log SEMUA service ke log deploy (penyebab sering ada di service belakang, mis. php-fpm di balik nginx; nilai rahasia disamarkan). */
    private function logTail(): void
    {
        try {
            $p = new Process($this->compose('logs', '--no-color', '--tail', '25'), $this->d->dir());
            $p->setTimeout(60)->run();
            foreach (preg_split('/\r?\n/', trim($p->getOutput().$p->getErrorOutput())) as $line) if ($line !== '') $this->log('health_check', 'err', $this->mask($line));
        } catch (\Throwable) {
        }
    }

    /** URL clone dari URL repo apa pun: buang akhiran .git/garis miring lalu tambahkan .git tepat satu kali (mis. ...dagopark.git.git dulu). */
    public static function cloneUrl(string $url): string
    {
        return preg_replace('#(\.git)?/*$#', '', trim($url)).'.git';
    }

    private function assertTagUnchanged(string $url, string $tag, string $sha): void
    {
        $out = $this->run(['git', 'ls-remote', $url, "refs/tags/$tag", "refs/tags/$tag^{}"], null, 'cloning', 60);
        $lines = array_filter(explode("\n", trim($out)));
        $now = null;
        foreach ($lines as $l) { // baris ^{} (peeled) menang untuk annotated tag
            [$h, $r] = preg_split('/\s+/', $l);
            if ($now === null || str_ends_with($r, '^{}')) $now = $h;
        }
        if ($now !== $sha) throw new \RuntimeException("Tag $tag dipindah (sekarang ".($now ?? 'hilang')."), deploy dibatalkan.");
    }

    /**
     * Folder aplikasi yang perlu .env sendiri: folder level 1–2 yang punya .env.example atau .env ter-commit, DAN yang
     * benar-benar dipakai compose (di-mount atau jadi konteks build). Folder tak terpakai (mis. src_old/) dilewati.
     */
    private function nestedEnvFiles(string $dir, array $compose): array
    {
        $roots = [];
        foreach ((array) ($compose['services'] ?? []) as $s) {
            $s = (array) $s;
            foreach ((array) ($s['volumes'] ?? []) as $vol) {
                $src = is_array($vol) ? (($vol['type'] ?? 'bind') === 'bind' ? ($vol['source'] ?? '') : '') : explode(':', (string) $vol)[0];
                if ($src !== '' && str_starts_with($src, '/')) $roots[] = rtrim($src, '/');
            }
            $ctx = is_array($s['build'] ?? null) ? ($s['build']['context'] ?? null) : ($s['build'] ?? null);
            if (is_string($ctx) && str_starts_with($ctx, '/')) $roots[] = rtrim($ctx, '/');
        }
        $used = fn (string $folder) => collect($roots)->contains(fn ($r) => $r === $folder || str_starts_with($folder, $r.'/') || str_starts_with($r, $folder.'/'));
        $found = [];
        foreach (['*', '*/*'] as $depth) {
            foreach (File::glob("$dir/$depth/.env.example") as $f) $found[dirname($f)] = dirname($f).'/.env';
            foreach (File::glob("$dir/$depth/.env") as $f) $found[dirname($f)] = $f;
        }
        return array_values(array_filter($found, fn ($path, $folder) => $used($folder) && ! preg_match('#/(node_modules|vendor)/#', $path), ARRAY_FILTER_USE_BOTH));
    }

    /** Tulis berkas tanpa mengikuti symlink: bila path sudah berupa symlink, putuskan dulu (jangan menulis ke targetnya). */
    public static function writeFile(string $path, string $text, int $mode): void
    {
        if (is_link($path)) unlink($path);
        file_put_contents($path, $text);
        chmod($path, $mode);
    }

    /**
     * Hapus semua symlink di folder clone yang menunjuk ke luar folder itu (atau menggantung). Docker mengikuti symlink
     * saat bind mount/build, dan penulisan env mengikuti symlink; keduanya bisa menjangkau berkas karya lain dan host.
     * @return list<string> path relatif yang dihapus
     */
    public static function stripEscapingSymlinks(string $dir): array
    {
        $root = realpath($dir) ?: throw new \RuntimeException("Folder $dir tidak ada.");
        $removed = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $f) => $f->isLink() || ! $f->isDir() || $f->getFilename() !== '.git'   // jangan masuk ke .git
        ), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            if (! $f->isLink()) continue;
            $real = realpath($f->getPathname());
            if ($real === false || ($real !== $root && ! str_starts_with($real, $root.'/'))) {
                unlink($f->getPathname());
                $removed[] = ltrim(substr($f->getPathname(), strlen($root)), '/');
            }
        }
        return $removed;
    }

    /**
     * Hapus folder karya. Di Docker rootless, berkas yang dibuat proses non-root di container (mis. data MariaDB)
     * dimiliki sub-uid dan tidak bisa dihapus `runner`; hapus lewat container (root di container = runner) bila perlu.
     */
    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) return;
        try { File::deleteDirectory($dir); } catch (\Throwable) {}   // folder milik sub-uid (mode 700) melempar galat; lanjut ke jalur container
        if (is_dir($dir)) {
            $p = new Process(['docker', 'run', '--rm', '--user', '0', '--entrypoint', 'sh', '-v', "$dir:/d", config('fasinova.probe_image'),
                '-c', 'rm -rf /d/* /d/.[!.]* /d/..?* 2>/dev/null; true']);
            $p->setTimeout(300)->run();
            try { File::deleteDirectory($dir); } catch (\Throwable) {}
        }
        if (is_dir($dir)) throw new \RuntimeException("Folder $dir tidak bisa dihapus.");
    }

    /** Hentikan dan buang container stack (tanpa -v) setelah deploy gagal; galat diabaikan agar tidak menutupi penyebab asli. */
    private function downQuietly(string $step = 'failed', string $msg = 'container dihentikan karena deploy gagal'): void
    {
        try {
            if (File::exists($this->d->dir().'/fasinova.compose.json')) {
                $this->run($this->compose('down', '--remove-orphans'), $this->d->dir(), $step, 180);
                $this->log($step, 'out', $msg);
            }
        } catch (\Throwable $e) {
            $this->log($step, 'err', 'gagal menghentikan container: '.$this->mask($e->getMessage()));
        }
    }

    /**
     * Pilih service yang dibuka ke publik dan port internalnya dari `ports:` yang dideklarasikan di compose
     * (mahasiswa tidak perlu tahu). Pilihan eksplisit (public_service) dihormati bila servicenya ada.
     * @return array{0:string,1:int}
     */
    public static function detectPublic(array $compose, ?string $chosen, int $fallbackPort): array
    {
        $services = $compose['services'] ?? [];
        $prefer = [80, 8080, 3000, 8000, 5173, 8501, 8888, 443];
        $dbPorts = [3306, 5432, 6379, 27017, 11211, 9200, 5672];
        $targets = function (array $s) use ($dbPorts) {
            $t = [];
            foreach ((array) ($s['ports'] ?? []) as $p) {
                $target = is_array($p) ? ($p['target'] ?? null) : (int) (explode(':', (string) $p)[count(explode(':', (string) $p)) - 1]);
                if ($target && ! in_array((int) $target, $dbPorts)) $t[] = (int) $target;
            }
            return $t;
        };
        $pick = function (array $t) use ($prefer, $fallbackPort) {
            foreach ($prefer as $p) if (in_array($p, $t)) return $p;
            return $t[0] ?? $fallbackPort;
        };
        if ($chosen && isset($services[$chosen])) {
            return [$chosen, $pick($targets((array) $services[$chosen]))];
        }
        $webNames = ['nginx', 'web', 'app', 'frontend', 'proxy', 'caddy', 'traefik', 'apache', 'httpd', 'gateway', 'server'];
        $best = null; $bestScore = -1;
        foreach ($services as $name => $s) {
            $t = $targets((array) $s);
            if (! $t) continue;
            $score = 10 + (in_array(strtolower($name), $webNames) ? 5 : 0) + (in_array(80, $t) || in_array(8080, $t) || in_array(3000, $t) ? 3 : 0);
            if ($score > $bestScore) { $best = $name; $bestScore = $score; }
        }
        if ($best !== null) return [$best, $pick($targets((array) $services[$best]))];
        return [array_key_first($services), $fallbackPort];
    }

    /** Decode JSON compose; objek kosong `{}` (mis. ipam) tetap objek, bukan berubah menjadi list `[]`. */
    public static function decodeCompose(string $json): array
    {
        $walk = function ($v) use (&$walk) {
            if ($v instanceof \stdClass) {
                return (array) $v === [] ? $v : array_map($walk, (array) $v);
            }
            return is_array($v) ? array_map($walk, $v) : $v;
        };
        $d = json_decode($json);
        return $d instanceof \stdClass ? $walk($d) : [];
    }

    private function allocatePort(): int
    {
        [$lo, $hi] = array_map('intval', explode('-', config('fasinova.port_range')));
        $used = Deployment::whereNotNull('host_port')->pluck('host_port')->all();
        for ($p = $lo; $p <= $hi; $p++) {
            if (in_array($p, $used)) continue;
            try { // kolom host_port unik: bila worker lain mengambil port yang sama, coba port berikutnya
                $this->d->update(['host_port' => $p]);
                return $p;
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            }
        }
        throw new \RuntimeException('Rentang port habis.');
    }

    private function compose(string ...$args): array
    {
        return ['docker', 'compose', '-p', $this->d->project(), '-f', $this->d->dir().'/fasinova.compose.json', '--project-directory', $this->d->dir(), ...$args];
    }

    /**
     * Peta env akhir: env mahasiswa; APP_URL dan ASSET_URL diisi alamat publik karya bila mahasiswa tidak mengisinya
     * (aplikasi sering menanam domain dev, mis. https://nama.test, sehingga aset gagal dimuat); lalu DB_* dari FASINOVA
     * menimpa DB_* buatan mahasiswa.
     * @return array<string,string>
     */
    public static function envMap($vars, array $dbEnv, string $publicUrl): array
    {
        $map = [];
        foreach ($vars as $v) $map[$v->key] = (string) $v->value;
        foreach (['APP_URL', 'ASSET_URL'] as $k) if (! isset($map[$k]) || $map[$k] === '') $map[$k] = $publicUrl;
        return $dbEnv + $map;
    }

    public static function envText(array $map): string
    {
        $q = fn ($k, $v) => $k.'="'.addcslashes((string) $v, "\"\\\n$").'"';
        return implode("\n", array_map($q, array_keys($map), $map))."\n";
    }

    private function step(string $s): void
    {
        $this->d->update(['status' => $s]);
        $this->log($s, 'out', "== $s ==");
    }

    private function run(array $cmd, ?string $cwd, string $step, int $timeout = 120): string
    {
        $p = new Process($cmd, $cwd, ['GIT_TERMINAL_PROMPT' => '0']);
        $p->setTimeout($timeout);
        $buf = '';
        $p->run(function ($type, $out) use ($step, &$buf) {
            if ($type === Process::OUT) $buf .= $out;
            foreach (preg_split('/\r?\n/', trim($out)) as $line) {
                if ($line !== '') $this->log($step, $type === Process::ERR ? 'err' : 'out', $this->mask($line));
            }
        });
        if (! $p->isSuccessful()) throw new \RuntimeException("Perintah gagal ({$p->getExitCode()}): ".implode(' ', array_slice($cmd, 0, 4)));
        return $buf;
    }

    /** Nilai env rahasia tidak boleh muncul di log. */
    private function mask(string $s): string
    {
        return $this->secrets ? str_replace($this->secrets, '***', $s) : $s;
    }

    private function log(string $step, string $stream, string $line): void
    {
        $this->d->logs()->create(['step' => $step, 'stream' => $stream, 'line' => $line]);
    }

    private function history(\DateTimeInterface $started, string $result): void
    {
        DeployHistory::create(['deployment_id' => $this->d->id, 'triggered_by' => $this->d->triggered_by, 'commit_sha' => $this->d->commit_sha,
            'result' => $result, 'duration_s' => (int) round(abs(now()->diffInSeconds($started)))]);
    }

    private function notify(string $title, string $body, string $status): void
    {
        $i = $this->d->innovation;
        Notify::innovation([...Notify::team($i), ...Notify::pics($i)], $i, $title, $i->title.' — '.$body, $status, $status === 'danger' ? '/admin/deployments' : null);
    }
}
