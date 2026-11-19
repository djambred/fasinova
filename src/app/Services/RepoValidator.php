<?php

namespace App\Services;

use App\Models\Innovation;
use Illuminate\Support\Facades\Http;

class RepoValidator
{
    private function gh(string $path)
    {
        $req = Http::acceptJson()->withHeaders(['User-Agent' => 'fasinova']);
        if ($t = config('fasinova.github_token')) $req = $req->withToken($t);
        return $req->get('https://api.github.com'.$path);
    }

    /**
     * Tebak stack dari isi repo (akar atau folder level 1–2): Laravel (artisan), Streamlit (requirements/pyproject menyebut streamlit),
     * JavaScript (package.json). Urutannya penting: repo Laravel juga punya package.json untuk aset. Null bila tak yakin.
     */
    private function detectStack($paths, string $owner, string $name, string $sha): ?string
    {
        $find = fn (string $f) => $paths->first(fn ($p) => $p === $f || (bool) preg_match('#^([^/]+/){1,2}'.preg_quote($f, '#').'$#', $p));
        if ($find('artisan')) return 'laravel';
        foreach (['requirements.txt', 'pyproject.toml'] as $f) {
            if (($p = $find($f)) && stripos((string) Http::get("https://raw.githubusercontent.com/$owner/$name/$sha/$p")->body(), 'streamlit') !== false) return 'streamlit';
        }
        return $find('package.json') ? 'js' : null;
    }

    /** Samakan stack karya dengan hasil deteksi (sistem yang menyesuaikan, bukan mahasiswa). Mengembalikan keterangan perubahan. */
    private function applyStack(Innovation $i, string $stack): string
    {
        $d = $i->deployment;
        if (! $d || $d->stack === $stack) return '';
        $old = $d->stack;
        $port = $d->internal_port;
        $d->update(['stack' => $stack, 'internal_port' => (blank($port) || $port == config("fasinova.stacks.$old.port")) ? config("fasinova.stacks.$stack.port") : $port]);
        return $old ? 'Stack disesuaikan otomatis dari '.config("fasinova.stacks.$old.label").'.' : 'Stack diisi otomatis.';
    }

    /** Validasi repo + simpan hasilnya. Mengembalikan item per-cek. */
    public function validate(Innovation $i): array
    {
        $repo = $i->repository;
        $track = $i->track;
        $items = [];
        $add = function (string $label, bool $ok, string $detail = '') use (&$items) {
            $items[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
            return $ok;
        };
        $sha = null;
        $envExample = null;
        $stack = null;
        $refType = null;

        // Terima apa pun yang wajar ditempel mahasiswa: akhiran .git, garis miring akhir, atau tautan halaman GitHub (/tree/<branch>/<folder>).
        // Disimpan dalam bentuk kanonik https://github.com/<pemilik>/<repo>; branch dari /tree/ dipakai bila kolom tag kosong.
        if ($add('Format URL', (bool) preg_match('#^https://github\.com/([\w.-]+)/([\w.-]+?)(?:\.git)?(?:/(?:tree|blob)/([^/]+)(?:/.*)?)?/?$#', trim((string) $repo->url), $m))) {
            [, $owner, $name] = $m;
            $urlRef = $m[3] ?? null;
            $repo->fill(['owner' => $owner, 'name' => $name, 'url' => "https://github.com/$owner/$name"]);
            $org = config('fasinova.github_org');
            $add('Organisasi prodi', $org && strcasecmp($owner, $org) === 0, "Harus di organisasi $org");
            $prefix = $track->repo_prefix;
            $add('Format nama repo', ! $prefix || (str_starts_with($name, $prefix) && $name === strtolower($name)), "Awali dengan '$prefix', huruf kecil");

            $r = $this->gh("/repos/$owner/$name");
            if ($add('Repo ditemukan & publik', $r->ok() && ! $r->json('private'), $r->ok() ? ($r->json('private') ? 'Repo privat ditolak' : '') : 'Repo tidak ditemukan atau privat')) {
                $want = trim((string) $repo->tag) ?: $urlRef;
                $refType = null;
                $path = fn (string $n) => str_replace('%2F', '/', rawurlencode($n));   // nama branch boleh berisi "/"
                if ($want) {
                    // Kolom "tag" menerima tag, branch, atau commit: mahasiswa sering mengisi nama branch (mis. main).
                    $ref = $this->gh("/repos/$owner/$name/git/ref/tags/".$path($want));
                    if ($ref->ok()) { $refType = 'tag'; $add("Tag $want ada", true); }
                    else {
                        $ref = $this->gh("/repos/$owner/$name/git/ref/heads/".$path($want));
                        if ($ref->ok()) { $refType = 'branch'; $add("Branch $want ada (bukan tag; versi terkini branch dikunci saat disetujui)", true); }
                        elseif (preg_match('/^[0-9a-f]{7,40}$/i', $want) && ($c = $this->gh("/repos/$owner/$name/commits/$want"))->ok()) {
                            $refType = 'commit'; $ref = $c; $add("Commit $want ada", true);
                        }
                    }
                    $found = $refType !== null;
                    if (! $found) $add("Tag/branch/commit '$want' ada", false, 'Tidak ditemukan sebagai tag, branch, maupun commit. Kosongkan kolom Tag untuk memakai branch default ('.($r->json('default_branch') ?: 'main').').');
                } else { // kosong: pakai branch default; SHA terkini dikunci saat PIC menyetujui
                    $branch = $r->json('default_branch') ?: 'main';
                    $ref = $this->gh("/repos/$owner/$name/git/ref/heads/".$path($branch));
                    $found = $add("Branch default ($branch) ada (tanpa tag)", $ref->ok());
                    $refType = $found ? 'default' : null;
                }
                if ($found) {
                    $sha = $refType === 'commit' ? $ref->json('sha') : $ref->json('object.sha');
                    if ($ref->json('object.type') === 'tag') {
                        $sha = $this->gh("/repos/$owner/$name/git/tags/$sha")->json('object.sha');
                    }
                    $tree = $this->gh("/repos/$owner/$name/git/trees/$sha?recursive=1");
                    $paths = collect($tree->json('tree', []))->pluck('path');
                    // File wajib dicari di akar repo atau di folder level 1–2 (mis. monorepo: src/.env.example).
                    $has = fn (string $f) => $paths->contains(fn ($p) => $p === $f || (bool) preg_match('#^([^/]+/){1,2}'.preg_quote($f, '#').'$#', $p));
                    $stack = $this->detectStack($paths, $owner, $name, $sha);
                    // Tiga berkas umum tidak menghalangi deploy bila FASINOVA bisa menutupinya sendiri: Dockerfile/compose dibuatkan
                    // untuk Streamlit, .env.example & README hanya pelengkap (aplikasi tanpa env tetap jalan). Berkas SOP lain tetap wajib.
                    $soft = ['.env.example' => 'Tidak ada; aplikasi dijalankan tanpa variabel tambahan.', 'README.md' => 'Tidak ada; sebaiknya ditambahkan untuk pengunjung.'];
                    foreach ($track->required_files ?? [] as $req) {
                        $found = collect(explode('|', $req))->contains($has);
                        if (! $found && $stack === 'streamlit' && str_contains($req, 'docker')) {
                            $items[] = ['label' => "File wajib: $req", 'ok' => true, 'warn' => true, 'detail' => 'Tidak ada; FASINOVA membuatkan Dockerfile + docker-compose.yml otomatis (Streamlit).'];
                        } elseif (! $found && isset($soft[$req])) {
                            $items[] = ['label' => "File wajib: $req", 'ok' => true, 'warn' => true, 'detail' => $soft[$req]];
                        } else {
                            $add("File wajib: $req", $found, 'Tidak ditemukan di akar repo maupun folder level 1–2');
                        }
                    }
                    // .env ter-commit bukan penghalang: deployer menimpanya dengan env dari FASINOVA. Hanya diberi tanda peringatan.
                    $committedEnv = $paths->first(fn ($p) => (bool) preg_match('#(^|/)\.env$#', $p));
                    if ($committedEnv) {
                        $items[] = ['label' => '.env ter-commit di repo', 'ok' => true, 'warn' => true,
                            'detail' => "$committedEnv akan ditimpa otomatis dengan variabel dari FASINOVA; kunci di dalamnya sebaiknya dianggap sudah bocor."];
                    }
                    $envExample = $paths->first(fn ($p) => (bool) preg_match('#^([^/]+/){0,2}\.env\.example$#', $p));
                    if ($stack) $items[] = ['label' => 'Stack terdeteksi: '.config("fasinova.stacks.$stack.label"), 'ok' => true, 'detail' => $this->applyStack($i, $stack)];
                    $raw = Http::get("https://raw.githubusercontent.com/$owner/$name/$sha/docker-compose.yml");
                    if ($raw->ok()) {
                        try {
                            $viol = ComposePolicy::check(ComposePolicy::parseYaml($raw->body()), $track->forbidden_images ?? []);
                        } catch (\Throwable $e) { $viol = ['docker-compose.yml tidak valid: '.$e->getMessage()]; }
                        $add('Kebijakan docker-compose', ! $viol, implode(' ', $viol));
                    }
                }
            }
        }

        $repo->forceFill([
            'validation_report' => $items, 'validated_at' => now(),
            'locked_commit_sha' => $repo->locked_commit_sha, // SHA dikunci saat PIC menyetujui, bukan di sini
        ])->save();
        // SHA terbaru dari tag disimpan di laporan untuk dikunci saat approve
        $repo->update(['validation_report' => ['items' => $items, 'tag_sha' => $sha, 'ref_type' => $refType ?? null, 'env_example' => $envExample ?? null, 'stack' => $stack ?? null, 'ok' => collect($items)->every('ok')]]);

        return $items;
    }
}
