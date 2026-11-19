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
        $i = $this->d->innovation;
        $repo = $i->repository;
        $this->secrets = $i->envVariables->where('is_secret', true)->pluck('value')->filter(fn ($v) => strlen($v) >= 4)->all();
        $this->d->update(['status' => 'queued', 'started_at' => $started, 'finished_at' => null, 'commit_sha' => $repo->locked_commit_sha]);

        try {
            $sha = $repo->locked_commit_sha ?: throw new \RuntimeException('SHA belum dikunci (karya belum disetujui).');
            $dir = $this->d->dir();

            $this->step('cloning');
            $this->assertTagUnchanged($repo->url, $repo->tag, $sha);
            File::deleteDirectory($dir);
            File::ensureDirectoryExists(dirname($dir));
            $this->run(['git', 'clone', '--quiet', $repo->url.'.git', $dir], null, 'cloning', 300);
            $this->run(['git', 'checkout', '--quiet', '--detach', $sha], $dir, 'cloning');
            $head = trim($this->run(['git', 'rev-parse', 'HEAD'], $dir, 'cloning'));
            if ($head !== $sha) throw new \RuntimeException("HEAD $head bukan SHA terkunci $sha.");

            $this->step('scanning');
            if (File::exists("$dir/.env")) throw new \RuntimeException('.env ter-commit di repo; rotasi kunci dan perbaiki repo.');
            $leak = new Process(['gitleaks', 'detect', '--source', $dir, '--no-banner', '--redact', '--exit-code', '1']);
            $leak->setTimeout(300)->run();
            if ($leak->getExitCode() === 1) {
                throw new \RuntimeException("gitleaks menemukan rahasia di repo; mahasiswa harus merotasi kunci.\n".$this->mask($leak->getOutput().$leak->getErrorOutput()));
            }
            if ($leak->getExitCode() > 1) throw new \RuntimeException('gitleaks gagal: '.$leak->getErrorOutput());

            $dbEnv = [];
            if ($this->d->db_engine) {
                $this->log('scanning', 'out', 'Menyiapkan database '.$this->d->db_engine);
                $dbEnv = (new DbProvisioner)->provision($this->d);
                $this->secrets[] = $dbEnv['DB_PASSWORD'];
            }
            File::put("$dir/.env", $this->envFile($i->envVariables, $dbEnv));
            chmod("$dir/.env", 0600);
            $json = $this->run(['docker', 'compose', '-p', $this->d->project(), '--project-directory', $dir, 'config', '--format', 'json'], $dir, 'scanning');
            $compose = self::decodeCompose($json) ?: throw new \RuntimeException('docker compose config tidak menghasilkan JSON.');
            $viol = ComposePolicy::check($compose, $i->track->forbidden_images ?? [], $dir);
            if ($viol) throw new \RuntimeException("Kebijakan compose dilanggar:\n- ".implode("\n- ", $viol));
            if ($i->track->code === 'cs' && ! $i->safe_service_ok) throw new \RuntimeException('Cyber Security: PIC belum menandai service aman.');

            $port = $this->d->host_port ?: $this->allocatePort();
            $public = $this->d->public_service ?: array_key_first($compose['services']);
            $internal = $this->d->internal_port ?: ($i->track->default_port ?: 8501);
            $this->d->update(['public_service' => $public, 'internal_port' => $internal]); // dipakai health check
            $final = ComposePolicy::harden($compose, $i->slug, $public, $internal, $port,
                (float) $this->d->cpu_limit, $this->d->mem_limit_mb, $this->d->allowed_caps ?? [], (bool) $this->d->db_engine);
            File::put("$dir/fasinova.compose.json", json_encode($final, JSON_UNESCAPED_SLASHES));

            $this->step('building');
            $this->run($this->compose('up', '-d', '--build'), $dir, 'building', config('fasinova.build_timeout'));

            $this->step('starting');
            $this->step('health_check');
            if (! $this->waitHealthy()) throw new \RuntimeException('Health check gagal setelah 10 percobaan.');

            $this->proxy->upsert($this->d->refresh());
            $url = 'https://'.$this->d->subdomain.'.'.config('fasinova.domain');
            $this->d->update(['status' => 'live', 'url' => $url, 'needs_redeploy' => false, 'health_failures' => 0, 'finished_at' => now()]);
            $this->history($started, 'live');
            $this->notify('Demo live', $url, 'success');
        } catch (\Throwable $e) {
            $this->log('failed', 'err', $this->mask($e->getMessage()));
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
        File::deleteDirectory($d->dir());
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
        $svc = $d->public_service;
        $port = $d->internal_port;
        if ($svc && $port) {
            $p = new Process(['docker', 'run', '--rm', '--network', $d->project().'_default', '--memory', '32m', '--pids-limit', '32',
                config('fasinova.probe_image'), '-sf', '-o', '/dev/null', '--max-time', '5', "http://$svc:$port".$d->health_path]);
            $p->setTimeout(90)->run();
            if ($p->isSuccessful()) return true;
            if (! in_array($p->getExitCode(), [125, 126, 127])) return false; // curl benar-benar gagal = tidak sehat
        }
        try {
            return Http::timeout(5)->get('http://'.config('fasinova.health_host', '127.0.0.1').":{$d->host_port}{$d->health_path}")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function waitHealthy(): bool
    {
        for ($n = 1; $n <= 10; $n++) {
            if (self::healthy($this->d)) return true;
            $this->log('health_check', 'out', "percobaan $n/10 belum sehat");
            sleep(6);
        }
        return false;
    }

    private function assertTagUnchanged(string $url, string $tag, string $sha): void
    {
        $out = $this->run(['git', 'ls-remote', $url.'.git', "refs/tags/$tag", "refs/tags/$tag^{}"], null, 'cloning', 60);
        $lines = array_filter(explode("\n", trim($out)));
        $now = null;
        foreach ($lines as $l) { // baris ^{} (peeled) menang untuk annotated tag
            [$h, $r] = preg_split('/\s+/', $l);
            if ($now === null || str_ends_with($r, '^{}')) $now = $h;
        }
        if ($now !== $sha) throw new \RuntimeException("Tag $tag dipindah (sekarang ".($now ?? 'hilang')."), deploy dibatalkan.");
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
            if (! in_array($p, $used)) { $this->d->update(['host_port' => $p]); return $p; }
        }
        throw new \RuntimeException('Rentang port habis.');
    }

    private function compose(string ...$args): array
    {
        return ['docker', 'compose', '-p', $this->d->project(), '-f', $this->d->dir().'/fasinova.compose.json', '--project-directory', $this->d->dir(), ...$args];
    }

    /** Variabel DB dari FASINOVA menimpa DB_* buatan mahasiswa. */
    private function envFile($vars, array $extra = []): string
    {
        $q = fn ($k, $v) => $k.'="'.addcslashes((string) $v, "\"\\\n$").'"';
        $lines = $vars->reject(fn ($v) => array_key_exists($v->key, $extra))->map(fn ($v) => $q($v->key, $v->value))->all();
        foreach ($extra as $k => $v) $lines[] = $q($k, $v);
        return implode("\n", $lines)."\n";
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
