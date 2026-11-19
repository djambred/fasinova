<?php

namespace App\Services;

use Symfony\Component\Yaml\Yaml;

/** Kebijakan compose mahasiswa (PRD 6.3) + pembuatan compose akhir yang sudah dikeraskan. */
class ComposePolicy
{
    /** @return list<string> pesan pelanggaran; kosong = lolos */
    public static function check(array $compose, array $forbiddenImages = [], ?string $workdir = null): array
    {
        $v = [];
        $services = $compose['services'] ?? [];
        if (count($services) > config('fasinova.max_services')) {
            $v[] = 'Lebih dari '.config('fasinova.max_services').' service.';
        }
        foreach ($services as $name => $s) {
            $s = (array) $s;
            if (! empty($s['privileged'])) $v[] = "$name: privileged tidak diizinkan.";
            if (! empty($s['devices'])) $v[] = "$name: devices tidak diizinkan.";
            foreach ((array) ($s['cap_add'] ?? []) as $cap) {
                if (! in_array(strtoupper($cap), config('fasinova.allowed_caps'))) $v[] = "$name: cap_add $cap tidak diizinkan.";
            }
            if (($s['network_mode'] ?? null) === 'host') $v[] = "$name: network_mode host tidak diizinkan.";
            if (($s['pid'] ?? null) === 'host') $v[] = "$name: pid host tidak diizinkan.";
            if (($s['ipc'] ?? null) === 'host') $v[] = "$name: ipc host tidak diizinkan.";
            foreach ((array) ($s['volumes'] ?? []) as $vol) {
                $src = is_array($vol) ? (($vol['type'] ?? 'bind') === 'bind' ? ($vol['source'] ?? '') : '') : explode(':', $vol)[0];
                if (str_contains($src, 'docker.sock')) { $v[] = "$name: mount docker.sock tidak diizinkan."; continue; }
                if ($workdir && str_starts_with($src, rtrim($workdir, '/').'/') && ($real = realpath($src)) !== false && ($root = realpath($workdir)) !== false
                    && $real !== $root && ! str_starts_with($real, $root.'/')) {
                    $v[] = "$name: mount '$src' mengarah ke luar folder karya (symlink) tidak diizinkan."; // Docker mengikuti symlink; teks path saja tidak cukup
                    continue;
                }
                $abs = str_starts_with($src, '/') || str_starts_with($src, '..') || str_starts_with($src, '~');
                $inside = $workdir && str_starts_with($src, rtrim($workdir, '/').'/') && ! str_contains($src, '..');
                if ($abs && ! $inside) $v[] = "$name: mount path host '$src' tidak diizinkan.";
            }
            $image = strtolower($s['image'] ?? '');
            foreach ($forbiddenImages as $pattern) {
                // cocokkan nama dengan/ tanpa tag: "kalilinux/*" dan "bkimminich/juice-shop"
                $p = strtolower($pattern);
                if ($image !== '' && (fnmatch($p, $image) || fnmatch($p.':*', $image))) {
                    $v[] = "$name: image $image dilarang untuk demo online (aturan SOP).";
                    break;
                }
            }
        }
        return array_values(array_unique($v));
    }

    public static function parseYaml(string $yaml): array
    {
        return (array) Yaml::parse($yaml);
    }

    /**
     * Ambil compose hasil `docker compose config`, buang ports/container_name buatan mahasiswa,
     * lalu tambahkan override PRD 6.3. Hasil ditulis sebagai JSON (YAML subset) dan dipakai `up -f`.
     */
    public static function harden(array $compose, string $slug, string $publicService, int $internalPort, int $hostPort, float $cpu, int $memMb, array $caps = [], bool $joinDbNetwork = false, array $env = []): array
    {
        unset($compose['name']);
        // `$` dilipat jadi `$$` agar tidak diinterpolasi ulang saat compose membaca berkas ini
        $env = array_map(fn ($v) => str_replace('$', '$$', (string) $v), $env);
        foreach ($compose['services'] as $name => &$s) {
            unset($s['ports'], $s['container_name']);
            // Env FASINOVA diteruskan sebagai environment container asli. Beberapa aplikasi menulis ulang .env sendiri saat start
            // (nilai dev tertanam); variabel environment yang sudah ada tidak ditimpa oleh .env (Laravel/Dotenv), jadi nilai FASINOVA menang.
            if ($env) $s['environment'] = array_merge((array) ($s['environment'] ?? []), $env);
            $s['restart'] = 'unless-stopped';
            $s['security_opt'] = ['no-new-privileges:true'];
            $s['cap_drop'] = ['ALL'];
            $s['cap_add'] = array_values(array_unique([...config('fasinova.base_caps'), ...$caps]));
            $s['mem_limit'] = $memMb.'m';
            $s['cpus'] = $cpu;
            $s['pids_limit'] = 256;
            $s['labels'] = (array) ($s['labels'] ?? []) + ['fasinova.slug' => $slug];
            $s['logging'] = ['driver' => 'json-file', 'options' => ['max-size' => '10m', 'max-file' => '3']];
            if ($joinDbNetwork) { // tetap di jaringan default + jaringan server DB karya
                $s['networks'] = ((array) ($s['networks'] ?? ['default' => null])) + [config('fasinova.karya_db.network') => null];
            }
            if ($name === $publicService) {
                $s['ports'] = [['target' => $internalPort, 'host_ip' => '127.0.0.1', 'published' => (string) $hostPort, 'protocol' => 'tcp']];
            }
        }
        if ($joinDbNetwork) {
            $net = config('fasinova.karya_db.network');
            $compose['networks'] = ((array) ($compose['networks'] ?? [])) + [$net => ['name' => $net, 'external' => true]];
        }
        return $compose;
    }
}
