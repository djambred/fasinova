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

        if ($add('Format URL', (bool) preg_match('#^https://github\.com/([\w.-]+)/([\w.-]+?)(?:\.git)?/?$#', (string) $repo->url, $m))) {
            [, $owner, $name] = $m;
            $repo->fill(['owner' => $owner, 'name' => $name]);
            $org = config('fasinova.github_org');
            $add('Organisasi prodi', $org && strcasecmp($owner, $org) === 0, "Harus di organisasi $org");
            $prefix = $track->repo_prefix;
            $add('Format nama repo', ! $prefix || (str_starts_with($name, $prefix) && $name === strtolower($name)), "Awali dengan '$prefix', huruf kecil");

            $r = $this->gh("/repos/$owner/$name");
            if ($add('Repo ditemukan & publik', $r->ok() && ! $r->json('private'), $r->ok() ? ($r->json('private') ? 'Repo privat ditolak' : '') : 'Repo tidak ditemukan atau privat')) {
                $ref = $this->gh("/repos/$owner/$name/git/ref/tags/".rawurlencode($repo->tag));
                if ($add("Tag {$repo->tag} ada", $ref->ok())) {
                    $sha = $ref->json('object.sha');
                    if ($ref->json('object.type') === 'tag') {
                        $sha = $this->gh("/repos/$owner/$name/git/tags/$sha")->json('object.sha');
                    }
                    $tree = $this->gh("/repos/$owner/$name/git/trees/$sha?recursive=1");
                    $paths = collect($tree->json('tree', []))->pluck('path');
                    foreach ($track->required_files ?? [] as $req) {
                        $alts = explode('|', $req);
                        $add("File wajib: $req", collect($alts)->contains(fn ($a) => $paths->contains($a)), 'Tidak ditemukan di tag');
                    }
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
        $repo->update(['validation_report' => ['items' => $items, 'tag_sha' => $sha, 'ok' => collect($items)->every('ok')]]);

        return $items;
    }
}
