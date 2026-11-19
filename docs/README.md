# FASINOVA v1.1

Laravel 13 + Filament 4 (PRD ditulis untuk Laravel 12; PHP 8.4 karena dependensi terbaru). Panel: `/app` (mahasiswa), `/admin` (PIC & admin), situs publik `/`.

## Jalankan
1. `cp .env.example .env` — isi `GITHUB_ORG`, `APP_DOMAIN`, `CLOUDFLARE_API_TOKEN`, `RUNNER_UID/GID`.
2. `docker compose up -d --build db redis app web worker scheduler` (jangan `up` tanpa nama service di Mac/dev: `deployer` dan `caddy` butuh server Linux dengan Docker rootless)
3. `docker compose exec app php artisan key:generate && docker compose exec app php artisan migrate --seed`
4. Login admin: `admin@fasinova.test` / `password` (**ganti**). Peminatan AI & CS sudah di-seed; lainnya diatur di Admin → Peminatan.
5. `deployer` + `caddy` hanya setelah server disiapkan (PRD §8.3) dan PoC minggu 1 lulus.

## Tes
`php vendor/bin/phpunit` (SQLite in-memory; 19 tes: kebijakan compose, klaim, otorisasi, review→kunci SHA→deploy job, rate limit).

## Belum / perlu diputuskan
- SSO dibatalkan: login hanya email + kata sandi di `/masuk`. Akun dibuat admin (Admin → Pengguna, atau impor CSV peserta TA); kata sandi awal semua akun `password` (tanpa email/SMTP); pengguna menggantinya lewat menu profil di tiap panel.
- Health check dari container `deployer` ke `127.0.0.1:<port>` host tidak menjangkau loopback host; selesaikan di PoC (`HEALTH_HOST`).
- Database karya: `karya-mariadb` dan `karya-postgres` berjalan di daemon rootless `runner` (bukan Docker utama, bukan `db` FASINOVA), di jaringan `fz-db`. Jalankan sekali di server: `DOCKER_HOST=unix:///run/user/<UID runner>/docker.sock KARYA_DB_ROOT_PASSWORD=... docker compose -f docker-compose.runner.yml up -d`. Mahasiswa memilih engine di form; saat deploy `deployer` membuat database+user per karya lewat `docker exec`, menggabungkan container karya ke `fz-db`, dan mengisi `DB_HOST` (nama container), `DB_*` otomatis; database dihapus saat stack dihapus. Tanpa port dibuka ke host. Diuji dengan Docker biasa (bukan rootless): akses ke DB sendiri OK, ke DB lain ditolak, dari luar `fz-db` tidak ter-resolve. Semua karya ber-DB berbagi jaringan `fz-db` sehingga saling melihat sebagai host jaringan (tanpa kredensial tetap tidak bisa masuk DB lain).
- Reverse proxy: Caddy (menggantikan NPM). Rute karya diatur lewat admin API Caddy di unix socket `/run/fasinova/caddy-admin.sock` (buat folder itu milik `runner`, mode 0750; di-mount ke `caddy` dan `deployer`). Sertifikat: `CADDY_TLS=acme` (Let's Encrypt per hostname via HTTP-01; dipakai di fasinova.my.id, DNS di Sumopod: `A @` + `A *`), `cloudflare` (wildcard, butuh token) atau `internal` (uji lokal). Diuji dengan Caddy sungguhan (rute, idempoten, 404 tak dikenal, redirect HTTP→HTTPS, pemulihan setelah restart); wildcard Cloudflare belum bisa diuji tanpa domain+token. Di macOS bind mount socket gagal (chmod) — uji lokal pakai named volume.
- Alur karya (opsi B): mahasiswa mengisi wizard → pembimbing menyetujui di `/dosen` → ajukan → PIC memvalidasi checklist SOP → saat PIC menyetujui: SHA terkunci, karya terbit (bila formulir persetujuan ada) dan worker `deployer` langsung deploy (Cyber Security hanya bila PIC menandai service aman).
- Bentuk karya (`config/fasinova.php` → `form_types`): hanya `web` yang punya alur deploy; bentuk lain tanpa langkah stack/DB/repo/env/subdomain. Langkah terakhir wizard = TOS persetujuan publikasi (dicentang, waktu dicatat); link jurnal opsional tampil di halaman detail.
- Notifikasi memakai lonceng di panel (tanpa email/SMTP). SMTP bisa dipasang kemudian lewat `.env` bila tersedia.
- Belum ada: CPU/RAM per stack & grafik, `compose ps`, auto-stop 60 hari tanpa kunjungan, dashboard ADM-04, ZIP kit SAL-02, pencarian tsvector.
