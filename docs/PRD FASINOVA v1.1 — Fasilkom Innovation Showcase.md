# PRD FASINOVA v1.1 — Fasilkom Innovation Showcase

Nov 19, 2026 · @Jefry Sunupurwa

## 1. Ringkasan dan keputusan kunci

FASINOVA v1.1 menerima paket karya showcase dari mahasiswa saat penyerahan berkas akhir TA, sesuai SOP per peminatan. Mahasiswa mengirim tautan repo GitHub **publik** dan variabel `.env`. Setelah PIC memvalidasi checklist SOP, **worker langsung men-deploy dan karya terbit otomatis**; PIC tetap bisa mengelola dari modul **Deploy** bergaya Portainer: daftar stack, status, start/stop/redeploy, log, dan pemakaian sumber daya. Karya berjalan di server yang sama dengan FASINOVA, dan subdomain dibuat otomatis di domain milik FASINOVA sendiri lewat Caddy (reverse proxy dengan HTTPS otomatis).

Menjalankan kode mahasiswa di server yang sama adalah risiko keamanan terbesar produk ini. Tanpa pemisahan, satu repo jahat atau ceroboh bisa mengambil alih server beserta database karya dan `.env` semua mahasiswa \[Pasti\]. Karena itu karya dijalankan di **Docker daemon rootless milik user `runner`**, terpisah dari Docker utama yang menjalankan FASINOVA. Jika container karya berhasil keluar, ia hanya mendapat hak user biasa, bukan root \[Kemungkinan Besar\].

| Keputusan | Pilihan v1.1 | Keyakinan |
| --- | --- | --- |
| Siapa mengirim karya | Mahasiswa, login email + kata sandi (akun dibuat admin dari daftar peserta TA; SSO dibatalkan); pembimbing menyetujui | \[Pasti\] (sesuai SOP) |
| Repo | Wajib **publik**, di organisasi GitHub prodi, tag `v1.0-showcase`; repo privat ditolak | \[Pasti\] |
| Pemeriksa | PIC per peminatan, **hanya memvalidasi checklist SOP**; perbaikan maksimal 7 hari | \[Pasti\] (sesuai SOP) |
| Deploy | **Otomatis oleh worker** begitu PIC memvalidasi checklist (dari commit SHA terkunci); Cyber Security hanya bila PIC menandai service aman; PIC/admin tetap bisa Redeploy/Stop/Hapus | \[Pasti\] |
| Tempat karya berjalan | Server yang sama, tetapi di Docker rootless user `runner`; FASINOVA di Docker utama | \[Kemungkinan Besar\], wajib dibuktikan lewat PoC minggu 1 |
| UI pengelolaan | Modul Deploy di panel Filament, meniru konsep Portainer (stack, container, log, env, resource); tanpa fitur console/exec | \[Pasti\] |
| Domain | Domain milik FASINOVA sendiri (mis. `fasinova.id`, perlu dibeli), DNS di Cloudflare, subdomain `<slug>.<domain>` | \[Pasti\] |
| Sertifikat | Satu sertifikat wildcard Let's Encrypt via DNS challenge Cloudflare di Caddy | \[Kemungkinan Besar\] |
| Cyber Security | Lab rentan tidak pernah di-deploy online (aturan SOP) | \[Pasti\] |
| Audiens | Calon mahasiswa, orang tua, tim marketing/sales, industri | \[Pasti\] |

Catatan: reverse proxy yang dipakai adalah **Caddy**, menggantikan Nginx Proxy Manager pada rancangan awal.

## 2. Tujuan, audiens, metrik, dan ruang lingkup

Tujuan v1.1: setiap karya TA layak tampil dikumpulkan dalam satu format seragam, bisa dicoba publik lewat demo online yang aman, dan bisa dibawakan tim marketing tanpa bantuan mahasiswa.

| Audiens | Yang mereka butuhkan dari FASINOVA |
| --- | --- |
| Calon mahasiswa & orang tua | Judul dan masalah dalam bahasa awam, video 2–3 menit, demo yang bisa dicoba |
| Tim marketing / sales | Mode sales: kalimat siap pakai, skrip demo, video cadangan, kit offline |
| Industri / mitra | Kartu hasil dengan angka jujur, tim, kontak kolaborasi |
| Mahasiswa | Satu tempat mengirim paket TA, status pemeriksaan yang jelas |
| PIC & admin | Checklist SOP per peminatan, tombol deploy, log |

Target metrik di bawah adalah usulan untuk disahkan prodi \[Menebak\].

| Metrik | Target per periode TA |
| --- | --- |
| Paket TA layak tampil yang dikirim lewat sistem | 100% |
| Paket lolos pemeriksaan PIC pada pengiriman pertama | ≥ 60% |
| Waktu PIC memeriksa (median) | ≤ 5 hari kerja |
| Karya web/demo yang berhasil ter-deploy dan lolos health check | ≥ 80% dari yang disetujui deploy |
| Demo online yang tetap hidup 30 hari setelah terbit | ≥ 95% |
| Inquiry kolaborasi dibalas dalam 5 hari kerja | ≥ 90% |

| Masuk v1.1 | Tidak masuk v1.1 |
| --- | --- |
| Login mahasiswa, dosen pembimbing, PIC, admin | Proposal hibah, review riset |
| Form paket per peminatan (AI, Cyber Security, Full Stack, Robotik/IoT, LLM/Chatbot) | CI/CD otomatis tiap push ke repo |
| Validasi repo via GitHub API (tag, file wajib) | Deploy dari repo privat atau di luar organisasi prodi |
| Pemeriksaan PIC dengan checklist SOP + tenggat 7 hari | Auto-scaling, multi-server runner |
| Deploy satu klik ke runner + subdomain via Caddy + log; database PostgreSQL atau MariaDB per karya (opsional) | Backup otomatis data database karya; database selain PostgreSQL/MariaDB |
| Etalase publik, detail karya, form kolaborasi | Pembayaran/lisensi online |
| Mode sales dan ekspor kit (ZIP materi) | Aplikasi mobile native |

## 3. Peran dan alur karya

Setelah PIC menyetujui, terbit dan deploy berjalan otomatis: karya terbit ke etalase bila formulir persetujuan publikasi ada, dan di-deploy bila peminatannya boleh deploy dan stack sudah dipilih. Karya tetap bisa tampil tanpa demo online; demo hanyalah tambahan bila karyanya aman dan bisa di-deploy.

&#91;embedded content: alur karya · dari kirim paket sampai tampil\]

Paket yang dikembalikan PIC wajib diperbaiki dalam 7 hari sesuai SOP; lewat tenggat, status menjadi terlambat dan PIC serta pembimbing diberi tahu.

| Peran | Siapa | Bisa melakukan |
| --- | --- | --- |
| Mahasiswa | Peserta TA (ketua tim) | Membuat karya, mengisi repo + env + materi, mengirim, memperbaiki |
| Pembimbing | Dosen pembimbing TA | Menyetujui paket sebelum masuk ke PIC; menandatangani ETIKA.md (Cyber Security) |
| PIC peminatan | Dosen yang ditunjuk prodi per peminatan | Memvalidasi checklist SOP, mengembalikan/menyetujui/menolak; mengelola deploy dan menarik karya dari etalase bila perlu |
| Admin | Unit inovasi / prodi | Konfigurasi peminatan, runner, domain, pengguna, inquiry, semua karya |
| Sales | Tim marketing | Membuka mode sales, mengunduh kit |
| Publik | Calon mahasiswa, orang tua, industri | Menjelajah, mencoba demo, mengirim form kolaborasi |

## 4. Paket karya per peminatan

Form pengiriman dan checklist PIC dibangkitkan dari konfigurasi per peminatan, sehingga SOP baru cukup ditambahkan sebagai data, bukan kode baru. Baris AI dan Cyber Security diambil dari SOP 15 Nov 2026; tiga baris lain menunggu SOP-nya \[Menebak\].

| Peminatan | Prefix repo | Demo online yang boleh di-deploy | Port default | File wajib di repo (dicek otomatis) |
| --- | --- | --- | --- | --- |
| AI (eksperimen algoritma) | `ai-` | Demo Streamlit/Gradio 3 tab, memuat model jadi, tanpa melatih ulang | 8501 | `Dockerfile`, `docker-compose.yml`, `requirements.txt`, `models/model.joblib` (atau tautan Releases), `reports/metrics.json`, `reports/dataset-card.md`, `data/sample.csv`, `showcase/kartu-karya.md` |
| Cyber Security | `cs-` | Hanya service aman yang ditandai PIC: dashboard mode replay, demo kriptografi, kuis awareness dengan merek fiktif. Lab rentan, penyerang, dan CTF **tidak pernah** di-deploy online | sesuai service | `docker-compose.yml`, `ETIKA.md`, `skenario/reset.sh`, `reports/`, `showcase/kartu-karya.md`, `showcase/skrip-demo.md` |
| Full Stack Development | menunggu SOP | Aplikasi web | menunggu SOP | `Dockerfile`/`docker-compose.yml`, `.env.example`, `README.md` \[Menebak\] |
| Robotik / IoT | menunggu SOP | Umumnya video + dashboard data rekaman; perangkat keras tidak bisa di-deploy \[Kemungkinan Besar\] | – | menunggu SOP |
| LLM / Chatbot | menunggu SOP | Antarmuka chat; perlu kebijakan kunci API dan batas biaya | menunggu SOP | menunggu SOP |

**Materi showcase** (diisi di FASINOVA, bukan hanya ada di repo). Wajib: bentuk karya, abstrak, gambar poster, kartu hasil, dan **persetujuan publikasi (TOS) yang dicentang** di langkah terakhir (waktu persetujuan dicatat). Opsional: HKI (nomor/keterangan bila sudah ada), **link jurnal bila sudah terpublikasi**, link YouTube video demo, tangkapan layar, dan formulir persetujuan bertanda tangan.

**Bentuk karya.** Mahasiswa memilih bentuk karya: Aplikasi web (demo online), Aplikasi mobile, Perangkat IoT/Robotik, Model/eksperimen riset (tanpa demo online), atau Lainnya. **Alur deploy otomatis (stack, database, GitHub, env, subdomain) hanya untuk Aplikasi web** pada peminatan yang boleh deploy; langkah-langkah itu disembunyikan untuk bentuk lain, yang belum memiliki alur khusus dan hanya mengisi data karya (pembimbing, abstrak, poster, kartu hasil, persetujuan). Alur untuk bentuk lain ditambahkan kemudian.

**Kartu hasil** diisi lewat form terstruktur, bukan unggahan bebas, supaya aturan klaim SOP bisa ditegakkan:

| Field | AI | Cyber Security |
| --- | --- | --- |
| Masalah & solusi (bahasa awam) | Wajib, ≤ 2 kalimat | Wajib, ≤ 2 kalimat |
| Data / lingkungan uji | Sumber, jumlah data, periode, lisensi/izin | Lab terisolasi, target, dataset + lisensi |
| Angka hasil | Tabel algoritma: baseline + terbaik; F1, recall, akurasi; n data uji | Jumlah serangan uji, terdeteksi, alarm palsu; temuan sebelum/sesudah per tingkat; waktu deteksi |
| Penjelasan awam | "Dari 100 kasus …, model mengenali sekitar …" | Analogi sederhana |
| Keterbatasan | Wajib diisi | Wajib diisi |
| Kalimat untuk sales | Maks. 3 kalimat | Maks. 3 kalimat |

Sistem memberi peringatan ke PIC bila teks memuat "100% aman", "tidak bisa diretas", "pasti", "selalu", "menggantikan manusia", atau persentase akurasi tanpa nama metrik, dan menolak kartu AI tanpa baris baseline.

## 5. Kebutuhan fungsional

Prioritas: **M** wajib v1.1, **S** sebaiknya, **C** bisa ditunda.

### 5.1 Panel mahasiswa `/app` (Filament)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| MHS-01 | Login tunggal `/masuk` dengan email + kata sandi (tanpa SSO); akun mahasiswa diimpor admin dari daftar peserta TA (NIM), kata sandi diatur lewat "Lupa kata sandi"; setelah login diarahkan ke panel sesuai peran | M |
| MHS-02 | Buat karya: pilih peminatan, **bentuk karya**, judul, dosen pembimbing; lalu isi sisanya dalam **wizard bertahap** (bentuk karya → stack & database → repo GitHub → env → subdomain → pembimbing & tim → abstrak, HKI & link jurnal → poster, video, berkas → kartu hasil → **persetujuan publikasi**); langkah stack sampai subdomain hanya muncul untuk bentuk Aplikasi web pada peminatan yang boleh deploy. Tombol Simpan tidak mewajibkan kelengkapan; kelengkapan dicek saat Kirim | M |
| MHS-03 | Isi **tautan repo GitHub** (`https://github.com/<org-prodi>/<prefix>-…`) dan **tag** (default `v1.0-showcase`). Sistem memvalidasi: repo publik (repo privat ditolak), ada di organisasi prodi, tag ada, nama sesuai format, file wajib ada, `docker-compose.yml` lolos kebijakan (bagian 6). Hasil validasi tampil per item | M |
| MHS-04 | **Editor env**: daftar pasangan KEY=VALUE, tombol "ambil dari `.env.example`" untuk mengisi kunci otomatis, tanda rahasia per kunci; nilai rahasia disamarkan setelah disimpan dan tidak pernah ditampilkan ulang | M |
| MHS-05 | Pilih **stack** (JavaScript/Node, Laravel, Python Streamlit; mengisi port internal awal otomatis) dan isi metadata deploy: service yang ditampilkan publik, port internal, path health check (default `/`), **subdomain demo** (dipilih mahasiswa: 3–40 karakter, unik, bukan kata cadangan atau terlarang) dan **database yang disediakan** (tidak ada / PostgreSQL / MariaDB, bagian 6.6) | M |
| MHS-06 | Isi abstrak, HKI (opsional), link jurnal (opsional), unggah gambar poster, tautan YouTube (opsional), dan isi kartu hasil terstruktur | M |
| MHS-07 | Kirim paket: wajib persetujuan pembimbing (dosen menyetujui di panelnya setelah menerima notifikasi dalam aplikasi) dan bentuk karya dipilih, abstrak, poster, kartu hasil terisi, dan **TOS persetujuan publikasi dicentang**; untuk Aplikasi web, validasi repo lolos serta stack dan subdomain terisi; untuk Cyber Security wajib unggah `ETIKA.md` bertanda tangan | M |
| MHS-08 | Lihat status, catatan PIC per item checklist, dan hitung mundur tenggat perbaikan 7 hari | M |
| MHS-09 | Lihat status deploy dan URL demo setelah terbit (log build hanya untuk admin/PIC) | S |

### 5.2 Panel PIC & admin `/admin` (Filament)

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| PIC-01 | Antrean pemeriksaan per peminatan; PIC hanya melihat peminatannya | M |
| PIC-02 | Checklist SOP per peminatan; tiap item Lulus / Perbaiki + catatan; hasil validasi otomatis ditampilkan di samping | M |
| PIC-03 | Aksi: Setujui (mengunci commit SHA dari tag saat itu, **lalu karya terbit dan di-deploy otomatis**), Kembalikan (tenggat 7 hari otomatis), Tolak | M |
| PIC-04 | **Deploy**: tombol Redeploy / Stop / Start / Hapus per karya (deploy pertama otomatis setelah PIC menyetujui); subdomain dipilih mahasiswa, PIC bisa mengubah lewat Redeploy; lihat log build dan log container secara langsung | M |
| PIC-05 | Cyber Security: saat menyetujui, PIC wajib menandai service aman; tanpa tanda ini karya **tidak** di-deploy otomatis dan tombol Deploy terkunci | M |
| PIC-06 | Terbit otomatis saat disetujui bila formulir persetujuan publikasi ada (jika belum, status Disetujui sampai ada); PIC bisa menarik karya dari etalase atau menerbitkannya manual | M |
| ADM-01 | Konfigurasi peminatan: prefix repo, file wajib, checklist, field kartu hasil, port default, boleh deploy atau tidak | M |
| ADM-02 | Pengaturan deploy: rentang port lokal, domain, kuota default per karya, cadangan sumber daya FASINOVA (token Cloudflare hanya di `.env`) | M |
| ADM-03 | Impor peserta TA (CSV: NIM, nama, prodi, peminatan, pembimbing), pengguna & peran | M |
| ADM-04 | Dashboard: paket per status per peminatan, karya terlambat diperbaiki, deploy gagal, pemakaian CPU/RAM runner | S |
| ADM-05 | Inquiry kolaborasi: status, penanggung jawab, catatan | M |
| ADM-06 | Log audit: siapa menyetujui, deploy, mengubah env, kapan | M |

### 5.3 Situs publik dan mode sales

| ID | Kebutuhan | Prio |
| --- | --- | --- |
| PUB-01 | Beranda FASINOVA: hero, pencarian, filter peminatan / prodi / tahun, grid karya | M |
| PUB-02 | Detail karya: bentuk karya, masalah & solusi awam, abstrak, HKI, **link jurnal (bila ada)**, video, tombol **"Coba demo"** ke subdomain (bila ter-deploy dan sehat), kartu hasil, tim + pembimbing, poster, form kolaborasi | M |
| PUB-03 | Tombol "Coba demo" otomatis disembunyikan bila health check gagal, diganti video | M |
| PUB-04 | Form kolaborasi dengan persetujuan UU PDP, Turnstile, rate limit 3/10 menit per IP | M |
| SAL-01 | **Mode sales** `/sales` (login tim marketing): daftar karya siap presentasi, kalimat sales, skrip demo, video cadangan, poster | S |
| SAL-02 | Unduh kit per karya (ZIP: video, poster, kartu hasil, skrip demo, README cara menjalankan offline) | C |

## 6. Modul Deploy (konsep Portainer) dan subdomain

Semua berjalan di satu server, tetapi dengan dua Docker daemon. FASINOVA dan Caddy berjalan di Docker utama; karya mahasiswa berjalan di Docker rootless milik user `runner`. Hanya container `deployer` yang memegang socket Docker rootless, dan tidak ada container yang memegang socket Docker utama.

&#91;embedded content: deploy di satu server · Docker utama + Docker rootless\]

Caddy memakai `network_mode: host` agar bisa meneruskan `<slug>.<domain>` ke port lokal karya (`127.0.0.1:20000–20999`). Container karya tidak bisa menjangkau loopback host, sehingga PostgreSQL dan Redis FASINOVA tidak terlihat dari karya \[Kemungkinan Besar\].

### 6.1 Fitur modul Deploy (gaya Portainer)

| Halaman | Isi | Aksi |
| --- | --- | --- |
| Daftar stack | Semua karya yang pernah di-deploy: subdomain, status (badge), CPU %, RAM, uptime, commit, deploy terakhir | Deploy, Redeploy, Start, Stop, Restart, Hapus; filter per peminatan/status |
| Detail stack | Daftar container (`compose ps`), port internal, health check terakhir, grafik CPU/RAM 24 jam | Restart per container |
| Log | Log build per deploy dan log runtime per container (tail 500 baris, ikuti langsung) | Unduh log |
| Env | Kunci env (nilai rahasia disamarkan); perubahan menandai stack "perlu redeploy" | Edit (pemilik & admin) |
| Riwayat | Setiap deploy: pemicu, SHA, durasi, hasil | Redeploy SHA yang sama |
| Sumber daya server | Total CPU/RAM/disk server, jatah karya vs FASINOVA, port terpakai | Ubah kuota per karya |

Sengaja **tidak** ada fitur console/exec ke container, upload file ke container, atau membuat stack dari YAML bebas: semua stack harus berasal dari repo karya yang sudah disetujui.

### 6.2 Alur satu kali deploy

1. PIC menyetujui checklist (atau menekan Redeploy); job masuk antrean `deploy` otomatis dengan SHA yang dikunci saat persetujuan. Deploy otomatis dilewati bila stack belum dipilih, peminatan tidak boleh deploy, atau Cyber Security belum ditandai aman.
2. Deployer meng-clone repo publik lewat HTTPS tanpa kredensial ke `/home/runner/apps/<slug>` dan memastikan HEAD = SHA terkunci.
3. Pemindaian rahasia dengan `gitleaks`: bila ada `.env` ter-commit atau kunci yang terdeteksi, deploy dibatalkan dan mahasiswa diminta merotasi kunci (repo publik berarti kunci itu sudah bocor).
4. Bila karya memilih database (6.6), deployer menyiapkan database + user karya dan menambahkan `DB_*` ke `.env`. Deployer menulis `.env` (mode `600`), menjalankan `docker compose config --format json`, lalu memeriksa kebijakan 6.3.
5. Deployer membuat override (kuota, port lokal, jaringan) dan menjalankan `docker compose -p fz-<slug> ... up -d --build` terhadap daemon rootless. Batas build 15 menit.
6. Health check ke `http://127.0.0.1:<port><path>` hingga 10 kali, jeda 6 detik.
7. FASINOVA membuat/memperbarui rute di Caddy lewat admin API: `<slug>.<domain>` → `127.0.0.1:<port>`.
8. Status **Live**; email ke mahasiswa dan PIC.

Status: `not_deployed → queued → cloning → scanning → building → starting → health_check → live`, atau `failed`; `live → stopped` lewat tombol atau otomatis setelah 3 kali health check gagal.

### 6.3 Kebijakan compose mahasiswa

| Ditolak | Alasan |
| --- | --- |
| `privileged`, `cap_add` di luar daftar izin, `devices` | Akses ke host |
| `network_mode: host`, `pid: host`, `ipc: host` | Menembus isolasi |
| Mount socket Docker atau path absolut host | Mengambil alih daemon |
| `ports:` buatan mahasiswa | Diganti otomatis dengan satu port lokal dari FASINOVA |
| `container_name:` | Dihapus; prefix `fz-<slug>` |
| Image terlarang untuk online (`bkimminich/juice-shop`, DVWA, Metasploitable, `kalilinux/*`) | Aturan SOP Cyber Security |
| Lebih dari 5 service | Kuota v1 \[Menebak\] |

Override yang ditambahkan:

```yaml
services:
  <setiap-service>:
    restart: unless-stopped
    security_opt: ["no-new-privileges:true"]
    cap_drop: [ALL]
    mem_limit: 512m
    cpus: 0.5
    pids_limit: 256
    labels: { fasinova.slug: "<slug>" }
    logging: { driver: json-file, options: { max-size: "10m", max-file: "3" } }
  <service-publik>:
    ports: ["127.0.0.1:<port-dari-FASINOVA>:<port-internal>"]
```

Batas CPU/RAM di Docker rootless hanya berlaku bila server memakai cgroup v2 dengan delegasi systemd untuk user `runner`; ini bagian dari PoC minggu 1 \[Kemungkinan Besar\]. PIC dapat menambah kapabilitas `CHOWN`, `SETUID`, `SETGID` per karya bila image gagal jalan karena `cap_drop`.

### 6.4 Domain, subdomain, dan Caddy

- **Domain**: domain milik FASINOVA (mis. `fasinova.id` atau `.my.id`), dibeli sendiri, DNS dikelola di Cloudflare (paket gratis cukup) \[Kemungkinan Besar\]. Record: `A @` dan `A *` ke IP publik server.
- **Subdomain karya**: `<slug>.<domain>`, huruf kecil/angka/tanda hubung, 3–40 karakter, unik; kata cadangan: `www`, `admin`, `api`, `mail`, `panel`, `app`, `sales`. FASINOVA sendiri di `<domain>` dan `www.<domain>`.
- **Sertifikat**: satu wildcard `*.<domain>` + `<domain>` via DNS challenge Cloudflare di Caddy lewat plugin DNS Cloudflare (token API Cloudflare dengan izin edit DNS untuk zona itu saja, disimpan di `.env`). Satu kebijakan TLS dipakai semua rute; Caddy memperbarui sertifikat otomatis. Akses HTTP dialihkan ke HTTPS (308).
- **Admin API Caddy**: hanya lewat **unix socket** (`/run/fasinova/caddy-admin.sock`, tanpa port jaringan) yang di-mount ke container `deployer`. Tiap karya = satu rute ber-ID `fz-<id deployment>` di level atas server `fz-server` (supaya Caddy mengelola sertifikat per hostname; `PUT /id/fz-server/routes/0` untuk membuat, `DELETE /id/fz-<id>` untuk menghapus; keduanya idempoten). Websocket (Streamlit) berjalan tanpa pengaturan tambahan. Job `CaddySync` tiap 5 menit memasang ulang rute demo live yang hilang (mis. setelah Caddy restart). Image Caddy dibuild dengan plugin `caddy-dns/cloudflare` (xcaddy); pin versi dan uji ulang tiap upgrade \[Kemungkinan Besar\].
- Tidak ada panel admin web untuk Caddy; pengelolaan rute lewat modul Deploy FASINOVA. Port 2019 tidak dipakai.

### 6.5 Kapasitas satu server

Contoh server 8 vCPU / 32 GB RAM / 500 GB SSD: cadangan FASINOVA + Caddy 2 vCPU / 6 GB; sisanya untuk karya dengan kuota default 0,5 vCPU / 512 MB, kira-kira 30–40 demo ringan bersamaan \[Menebak\]. Demo yang tidak dibuka 60 hari dihentikan otomatis (video tetap tampil).

### 6.6 Database karya (PostgreSQL dan MariaDB)

Mahasiswa Full Stack memilih salah satu dari dua engine yang disediakan FASINOVA; karya lain boleh tidak memakai database atau membawa database sendiri di compose-nya (dihitung dalam batas 5 service).

| Aspek | Ketentuan |
| --- | --- |
| Tempat | Dua container, `karya-postgres` (PostgreSQL 16) dan `karya-mariadb` (MariaDB 11), di **Docker rootless user `runner`**, jaringan `fz-db`. **Bukan** `db` milik FASINOVA dan bukan di Docker utama. Dijalankan sekali lewat `docker-compose.runner.yml` (8.3) |
| Isolasi | Satu database + satu user per karya (`fz_<slug>`, huruf kecil/angka/underscore, maks. 32 karakter). User hanya punya hak atas database itu; Postgres: akses ke database `postgres`/`template1` dicabut dan database dicabut dari `PUBLIC` |
| Jaringan | Tidak ada port yang dibuka ke host. Deployer menambahkan jaringan `fz-db` (eksternal) ke semua service karya yang memilih database; container tanpa jaringan itu tidak bisa me-resolve server DB. Semua karya ber-database berbagi `fz-db`, sehingga saling melihat sebagai host jaringan tetapi tanpa kredensial tidak bisa masuk database lain |
| Kredensial | Dibuat acak (24 karakter alfanumerik) saat deploy pertama, disimpan terenkripsi di tabel `deployments`, dan **dipertahankan antar-redeploy** agar data tetap terhubung. Disuntikkan ke `.env` karya: `DB_CONNECTION` (`mysql`/`pgsql`), `DB_HOST` (`karya-mariadb`/`karya-postgres`), `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Variabel `DB_*` buatan mahasiswa ditimpa. Nilai tidak muncul di log |
| Pembuatan | Deployer memegang socket rootless dan memakai `docker exec` ke server DB; SQL lewat stdin, kata sandi admin lewat variabel lingkungan `exec` |
| Penghapusan | Aksi **Hapus** pada stack menjalankan `DROP DATABASE` dan `DROP USER`; data hilang dan tidak bisa dipulihkan. **Stop** tidak menghapus data |
| Sumber daya | Tiap server DB dibatasi 1 GB RAM; dihitung dalam cadangan kapasitas runner (6.5) |

Backup data database karya tidak termasuk v1.1 (pertanyaan terbuka); runner tidak di-backup (bagian 9).

## 7. Model data

Tabel utama di PostgreSQL. Konfigurasi peminatan disimpan sebagai data (`jsonb`) agar SOP baru tidak butuh migrasi.

| Tabel | Kolom kunci | Catatan |
| --- | --- | --- |
| `users` | name, email, nim (nullable, mahasiswa), kode\_dosen (nullable, dosen/PIC), study\_program\_id | peran via spatie: mahasiswa, pembimbing, pic, admin, sales |
| `tracks` (peminatan) | code (ai, cs, fullstack, iot, llm), name, repo\_prefix, default\_port, deployable, required\_files (jsonb), checklist (jsonb), result\_card\_schema (jsonb), forbidden\_images (jsonb), sop\_url | dikelola admin |
| `track_pics` | track\_id, user\_id | PIC per peminatan |
| `innovations` | id (uuid), slug, title, track\_id, study\_program\_id, academic\_period, problem, solution, status, supervisor\_id, supervisor\_approved\_at, revision\_due\_at, published\_at, search\_vector | status: draft, submitted, needs\_revision, approved, rejected, published, archived |
| `innovation_members` | innovation\_id, user\_id / nim, name, role, consent\_at | ketua, anggota |
| `repositories` | innovation\_id, url, owner, name, tag, locked\_commit\_sha, validated\_at, validation\_report (jsonb) | hasil cek GitHub API |
| `env_variables` | innovation\_id, key, value (encrypted), is\_secret, updated\_by | nilai terenkripsi APP\_KEY |
| `deployments` | innovation\_id, subdomain, public\_service, internal\_port, host\_port, health\_path, status, commit\_sha, url, cpu\_limit, mem\_limit\_mb, allowed\_caps (jsonb), db\_engine (nullable: postgres/mariadb), db\_name, db\_user, db\_password (terenkripsi), started\_at, finished\_at, last\_health\_at, triggered\_by | satu baris aktif per karya |
| `deployment_logs` | deployment\_id, step, stream, line, created\_at | log build/run; dipangkas setelah 30 hari |
| `result_cards` | innovation\_id, data (jsonb sesuai `result_card_schema`), claim\_warnings (jsonb) | kartu hasil terstruktur |
| `reviews` | innovation\_id, reviewer\_id, checklist\_result (jsonb), decision, note | riwayat pemeriksaan PIC |
| `inquiries`, `inquiry_notes` | sama seperti v1 | form kolaborasi |
| `media`, `activity_log` | spatie | video, poster, tangkapan layar, formulir persetujuan, ETIKA.md |

Indeks penting: `deployments(status)`, `deployments(subdomain)` unik, `innovations(track_id, status)`, GIN pada `innovations.search_vector`.

## 8. Infrastruktur: Dockerfile, docker-compose, dan persiapan server

Satu server, satu file compose untuk FASINOVA + Caddy di Docker utama, dan satu Docker rootless untuk karya yang disiapkan sekali di host. Versi image adalah titik awal dan harus di-pin saat kickoff \[Kemungkinan Besar\].

Stack: PHP 8.3+, Laravel 12.x, Filament 4.x, Livewire 3, PostgreSQL 16, Redis 7. Paket tambahan: `symfony/process` (menjalankan `git`/`docker compose`), `symfony/yaml`, HTTP client untuk GitHub API dan admin API Caddy, Laravel Reverb atau polling Livewire untuk log langsung.

### 8.1 Dockerfile

```dockerfile
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

FROM php:8.3-fpm-alpine
ARG GITLEAKS_VERSION=8.21.2   # contoh; pin versi yang diuji
RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp freetype postgresql-libs \
      git docker-cli docker-cli-compose curl \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev libpng-dev \
      libjpeg-turbo-dev libwebp-dev freetype-dev postgresql-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) pdo_pgsql intl zip gd bcmath exif opcache pcntl \
 && pecl install redis && docker-php-ext-enable redis \
 && curl -sSL https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz \
      | tar -xz -C /usr/local/bin gitleaks \
 && apk del .build
WORKDIR /var/www/html
COPY docker/php/*.ini /usr/local/etc/php/conf.d/
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN php artisan package:discover && php artisan filament:assets \
 && php artisan storage:link && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]
```

### 8.2 docker-compose.yml

```yaml
name: fasinova

x-app: &app
  build: .
  image: fasinova/app:${APP_VERSION:-dev}
  env_file: .env
  volumes:
    - storage:/var/www/html/storage
  depends_on:
    db: { condition: service_healthy }
    redis: { condition: service_healthy }
  restart: unless-stopped

services:
  app:
    <<: *app

  worker:
    <<: *app
    command: php artisan queue:work redis --queue=default --tries=3

  deployer:                       # satu-satunya yang memegang socket Docker rootless
    <<: *app
    command: php artisan queue:work redis --queue=deploy --tries=1 --timeout=1200
    user: "${RUNNER_UID}:${RUNNER_GID}"
    environment:
      DOCKER_HOST: unix:///run/user/${RUNNER_UID}/docker.sock
    volumes:
      - storage:/var/www/html/storage
      - /run/user/${RUNNER_UID}/docker.sock:/run/user/${RUNNER_UID}/docker.sock
      - /home/runner/apps:/home/runner/apps   # path sama di host & container

  scheduler:
    <<: *app
    command: php artisan schedule:work   # health check demo tiap 5 menit, sitemap, auto-stop

  web:
    image: nginx:1.27-alpine
    ports: ["127.0.0.1:8080:80"]        # hanya untuk Caddy
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
      - storage:/var/www/html/storage:ro
    depends_on: [app]
    restart: unless-stopped

  caddy:
    build: ./docker/caddy                     # Caddy + plugin DNS Cloudflare (xcaddy); pin versi yang diuji
    network_mode: host                        # bisa meneruskan ke 127.0.0.1:<port>
    environment:
      APP_DOMAIN: ${APP_DOMAIN}
      CLOUDFLARE_API_TOKEN: ${CLOUDFLARE_API_TOKEN}
    volumes:
      - caddy-data:/data
      - /run/fasinova:/run/fasinova           # socket admin API (hanya deployer yang ikut me-mount)
    restart: unless-stopped

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes: [pg-data:/var/lib/postgresql/data]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 10s
      retries: 5
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes: [redis-data:/data]
    healthcheck: { test: ["CMD", "redis-cli", "ping"], interval: 10s }
    restart: unless-stopped

volumes:
  storage:
  pg-data:
  redis-data:
  caddy-data:
```

Variabel `.env` tambahan: `RUNNER_UID`, `RUNNER_GID`, `RUNNER_APPS_DIR=/home/runner/apps`, `DEPLOY_PORT_RANGE=20000-20999`, `APP_DOMAIN`, `CLOUDFLARE_API_TOKEN`, `CADDY_SOCKET_DIR=/run/fasinova`, `KARYA_DB_ROOT_PASSWORD` (kata sandi admin kedua server DB karya; dipakai deployer dan `docker-compose.runner.yml`), `GITHUB_ORG`, `GITHUB_TOKEN` (token baca saja, hanya untuk menaikkan batas API; clone tidak memakai token karena repo publik).

Karena `deployer` memakai image yang sama dan berjalan sebagai UID `runner`, folder `storage` harus bisa ditulis UID itu, atau deployer menulis status lewat database saja. Ditetapkan saat PoC.

### 8.3 Persiapan server (sekali)

1. Ubuntu LTS, Docker Engine + Compose v2 untuk Docker utama.
2. Buat user `runner` tanpa sudo; pasang Docker rootless untuk user itu (`dockerd-rootless-setuptool.sh install`) dan aktifkan `loginctl enable-linger runner` agar daemon tetap hidup.
3. Aktifkan cgroup v2 dan delegasi systemd (`Delegate=cpu cpuset io memory pids`) untuk user `runner`, supaya `mem_limit`/`cpus` berlaku \[Kemungkinan Besar\].
4. Buat `/home/runner/apps` milik `runner`.
5. Firewall: masuk 80/443 publik; 22 dan 81 hanya dari IP admin; port 20000–20999 tidak dibuka (terikat ke 127.0.0.1).
6. Domain: beli domain, arahkan nameserver ke Cloudflare, buat record `A @` dan `A *` ke IP server, buat token API Cloudflare (edit DNS zona itu), lalu isi `CLOUDFLARE_API_TOKEN`; Caddy menerbitkan sertifikat wildcard sendiri. Buat `/run/fasinova` milik user `runner` (mode 0750) untuk socket admin Caddy.
7. Jadwalkan `docker system prune` mingguan untuk kedua daemon; peringatan saat disk > 80%.
8. Nyalakan server database karya sekali: `DOCKER_HOST=unix:///run/user/<UID runner>/docker.sock KARYA_DB_ROOT_PASSWORD=... docker compose -f docker-compose.runner.yml up -d` (membuat jaringan `fz-db`, container `karya-mariadb` dan `karya-postgres`, dan dua volume data).

**PoC minggu 1 (syarat lanjut)**: deploy satu demo Streamlit contoh lewat Docker rootless, buktikan (a) Caddy menjangkau `127.0.0.1:<port>`, (b) websocket berjalan lewat HTTPS, (c) batas RAM dipatuhi, (d) container tidak bisa menjangkau PostgreSQL/Redis FASINOVA, (e) container karya di Docker rootless bergabung ke jaringan `fz-db`, menjangkau `karya-mariadb`/`karya-postgres` lewat nama container, dan tidak bisa membuka database karya lain. Bila (c) atau (d) gagal, cadangannya adalah VM terpisah seperti rancangan sebelumnya.

## 9. Kebutuhan non-fungsional dan keamanan

Keamanan runner adalah kebutuhan nomor satu; kecepatan halaman publik nomor dua.

| Kategori | Kebutuhan | Target / bukti |
| --- | --- | --- |
| Isolasi | Kode mahasiswa hanya berjalan di Docker rootless user runner; tidak ada container yang memegang socket Docker utama | Uji: container app tidak bisa menjalankan `docker` |
| Kebijakan compose | Semua aturan 6.2 diuji dengan compose contoh yang melanggar | 100% contoh pelanggaran ditolak |
| Database karya | Server DB karya hanya di Docker rootless `runner`; satu database + user per karya; tanpa port ke host; kredensial terenkripsi dan tidak di log | Uji: user karya A ditolak ke database karya B dan ke `postgres`/`mysql`; container di luar `fz-db` tidak me-resolve `karya-*` |
| Egress | Demo tidak bisa menjangkau PostgreSQL, Redis, atau port lokal FASINOVA; koneksi keluar ke internet dipantau, belum diblokir di v1.1 | Uji: `curl https://example.com` dari container demo gagal |
| Rahasia | Env terenkripsi saat disimpan; tidak muncul di log deploy, log aplikasi, atau respons API | Uji: string env uji tidak ditemukan di log |
| Integritas | Deploy hanya dari commit SHA terkunci | Uji: tag dipindah → deploy dibatalkan |
| Otorisasi | Mahasiswa hanya melihat karyanya; PIC hanya peminatannya; deploy hanya PIC/admin | Policy + test Pest per peran |
| Ketersediaan demo | Health check tiap 5 menit; demo mati otomatis disembunyikan | Tombol "Coba demo" hilang ≤ 15 menit setelah demo mati |
| Kinerja publik | LCP < 2,5 s di 4G untuk beranda dan detail | Lighthouse mobile Performance ≥ 80 \[Menebak\] |
| Privasi (UU PDP No. 27/2022) | Formulir persetujuan publikasi wajib sebelum terbit; data pribadi dilarang di data demo (SOP) | Checklist PIC |
| Audit | Approve, deploy, stop, ubah env, ubah kuota tercatat dengan pelaku dan waktu; disimpan ≥ 2 tahun | Log aktivitas |
| Backup | `pg_dump` harian + volume `storage`; runner tidak di-backup karena bisa dibangun ulang dari repo | Uji restore sebelum go-live |
| Mutu kode | Pest, Larastan level 6, Pint di CI; job deploy diuji dengan runner staging | CI hijau sebelum rilis |

## 10. Roadmap

Deploy otomatis menambah sekitar 4 minggu dibanding versi tanpa deploy: total 10 minggu dengan 1–2 developer Laravel dan satu server serta domain milik sendiri \[Menebak\].

&#91;embedded content: roadmap v1.1 · 10 minggu, 2 jalur, 3 gerbang\]

G2 adalah gerbang keamanan: tombol Deploy tidak dibuka untuk karya mahasiswa sebelum kriteria penerimaan 5–10 lulus di runner staging. Bila G2 molor, etalase tetap bisa rilis dengan video saja.

## 11. Risiko, pertanyaan terbuka, dan kriteria penerimaan

Dua risiko paling mahal: runner disalahgunakan oleh kode karya, dan demo online yang mati atau memalukan di depan calon mahasiswa.

| Risiko | Peluang | Dampak | Mitigasi |
| --- | --- | --- | --- |
| Repo berisi kode jahat atau penambang kripto | Sedang | Tinggi | Docker rootless user runner, kuota CPU, gitleaks, deploy hanya setelah PIC setuju, `docker stats` + peringatan CPU tinggi |
| Mahasiswa memindahkan tag setelah disetujui | Sedang | Sedang | SHA dikunci; beda SHA → deploy batal |
| `.env` berisi kunci API berbayar (mis. LLM) bocor atau dihabiskan pengunjung | Tinggi | Sedang | SOP LLM perlu menetapkan batas biaya; env disamarkan; rekomendasikan kunci khusus showcase dengan limit |
| Image karya terlalu berat (PyTorch GPU, data mentah) | Tinggi | Sedang | Batas waktu build 15 menit, kuota disk; SOP AI sudah mewajibkan versi CPU |
| Lab Cyber Security ikut ter-deploy online | Rendah | Tinggi | Daftar image terlarang, tanda "service aman" wajib dari PIC, tombol Deploy terkunci tanpa tanda |
| Admin API atau plugin Caddy berubah setelah upgrade | Rendah | Sedang | Pin versi Caddy dan plugin; uji integrasi di staging sebelum upgrade |
| Kapasitas runner habis menjelang pameran | Sedang | Sedang | Demo yang tidak dibuka 60 hari dihentikan otomatis (video tetap tampil); runner kedua di v2 |
| SOP Full Stack, IoT, LLM belum ada | Pasti | Sedang | Konfigurasi peminatan dibuat sebagai data; tiga peminatan diaktifkan setelah SOP terbit |
| Kredensial atau data database karya bocor ke karya lain di jaringan `fz-db` | Rendah | Sedang | Satu user per database tanpa hak lintas-database, akses `postgres`/`template1` dicabut, kata sandi acak terenkripsi; jaringan `fz-db` hanya berisi server DB dan karya ber-database |
| Karya menghabiskan CPU/RAM sehingga FASINOVA ikut lambat atau mati (satu server) | Sedang | Tinggi | Kuota per karya lewat cgroup v2, cadangan sumber daya untuk FASINOVA, auto-stop demo yang melebihi batas, peringatan ke admin |
| Container karya lolos dari isolasi rootless | Rendah | Tinggi | Penyerang hanya mendapat hak user runner; update kernel dan Docker rutin; bila risiko tak bisa diterima, pindahkan karya ke VM terpisah |

**Pertanyaan terbuka**

- [ ] Nama domain FASINOVA yang akan dibeli (mis. `fasinova.id` atau `fasinova.my.id`), siapa pemilik akunnya, dan apakah DNS dikelola di Cloudflare?
- [ ] Server mana yang dipakai (milik prodi, lab, atau VPS sewa), dengan spesifikasi berapa, dan apakah kernelnya mendukung cgroup v2?
- [ ] Nama organisasi GitHub prodi; apakah repo boleh di akun pribadi mahasiswa selama publik?
- [ ] Siapa PIC tiap peminatan?
- [ ] Apakah data database karya perlu di-backup (dump mingguan dari `karya-postgres` dan `karya-mariadb`), dan berapa lama data dipertahankan setelah mahasiswa lulus?
- [ ] Berapa lama demo dibiarkan online setelah mahasiswa lulus?
- [ ] Kirim SOP Full Stack, Robotik/IoT, LLM/Chatbot, dan SOP umum (tenggat, formulir).

**Kriteria penerimaan v1.1**

1. Mahasiswa login, membuat karya AI, memasukkan tautan repo contoh; validasi menandai file wajib yang hilang dan tag yang tidak ada.
2. Tombol "ambil dari `.env.example`" mengisi kunci env; nilai rahasia tidak tampil lagi setelah disimpan dan tidak muncul di log mana pun.
3. Paket tidak bisa dikirim tanpa persetujuan pembimbing dan formulir persetujuan publikasi.
4. PIC mengembalikan paket; mahasiswa melihat catatan per item dan tenggat 7 hari; status otomatis "terlambat" bila lewat.
5. PIC menyetujui; SHA terkunci; Deploy menghasilkan demo Streamlit contoh yang terbuka di `https://<slug>.<domain>` dengan HTTPS valid dan websocket berfungsi, dalam ≤ 10 menit.
6. Compose contoh dengan `privileged: true`, mount `docker.sock`, atau `network_mode: host` ditolak dengan pesan yang jelas.
7. Container demo tidak bisa menjangkau PostgreSQL, Redis, atau port lokal FASINOVA; repo dengan .env ter-commit ditolak oleh gitleaks.
8. Stop dan Hapus menghentikan container; Hapus juga membuang database karya dan mengganti rute Caddy dengan jawaban 404 (sertifikat tetap ada); subdomain mengembalikan 404.
9. Demo yang dimatikan manual di runner terdeteksi health check; tombol "Coba demo" diganti video dalam ≤ 15 menit.
10. Karya Cyber Security dengan image `bkimminich/juice-shop` tidak bisa di-deploy.
11. Karya Full Stack contoh memilih MariaDB (lalu PostgreSQL): deploy mengisi `DB_*` otomatis, aplikasi bisa membuat tabel dan menyimpan data; kredensial karya itu tidak bisa membuka database karya lain; redeploy mempertahankan data; **Hapus** menghilangkan database dan user.
