<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $common = ['Repo & tag valid (otomatis; hanya aplikasi web)', 'Abstrak jelas dan tidak berlebihan', 'Gambar poster layak tampil',
            'Video demo / link YouTube (jika ada) sesuai karya', 'Kartu hasil sesuai aturan klaim', 'Persetujuan publikasi (TOS) dicentang', 'Tidak ada data pribadi di data demo'];
        $card = fn (array $f) => collect($f)->map(fn ($l, $k) => ['key' => $k, 'label' => $l, 'required' => true])->values()->all();

        $tracks = [
            ['code' => 'ai', 'name' => 'AI (eksperimen algoritma)', 'repo_prefix' => 'ai-', 'default_port' => 8501, 'deployable' => true,
             'required_files' => ['Dockerfile', 'docker-compose.yml', 'requirements.txt', 'models/model.joblib', 'reports/metrics.json',
                 'reports/dataset-card.md', 'data/sample.csv', 'showcase/kartu-karya.md'],
             'checklist' => $common,
             'forbidden_images' => [],
             'result_card_schema' => $card(['masalah_solusi' => 'Masalah & solusi (bahasa awam, ≤ 2 kalimat)', 'data_uji' => 'Data uji: sumber, jumlah, periode, lisensi/izin',
                 'baseline' => 'Baris baseline (algoritma pembanding)', 'angka_hasil' => 'Algoritma terbaik: F1, recall, akurasi, n data uji',
                 'penjelasan_awam' => 'Penjelasan awam ("Dari 100 kasus …")', 'keterbatasan' => 'Keterbatasan', 'kalimat_sales' => 'Kalimat untuk sales (maks. 3 kalimat)'])],
            ['code' => 'cs', 'name' => 'Cyber Security', 'repo_prefix' => 'cs-', 'deployable' => true,
             'required_files' => ['docker-compose.yml', 'ETIKA.md', 'skenario/reset.sh', 'reports', 'showcase/kartu-karya.md', 'showcase/skrip-demo.md'],
             'checklist' => [...$common, 'ETIKA.md bertanda tangan pembimbing', 'Service aman ditandai PIC'],
             'forbidden_images' => ['bkimminich/juice-shop', 'vulnerables/web-dvwa', '*dvwa*', 'tleemcjr/metasploitable2', '*metasploitable*', 'kalilinux/*'],
             'result_card_schema' => $card(['masalah_solusi' => 'Masalah & solusi (bahasa awam, ≤ 2 kalimat)', 'lingkungan_uji' => 'Lab terisolasi, target, dataset + lisensi',
                 'angka_hasil' => 'Serangan uji, terdeteksi, alarm palsu, temuan sebelum/sesudah, waktu deteksi', 'analogi' => 'Analogi sederhana',
                 'keterbatasan' => 'Keterbatasan', 'kalimat_sales' => 'Kalimat untuk sales (maks. 3 kalimat)'])],
            // Tiga peminatan menunggu SOP; diaktifkan lewat panel admin (ADM-01).
            ['code' => 'fullstack', 'name' => 'Full Stack Development', 'deployable' => true,
             'required_files' => ['Dockerfile|docker-compose.yml', '.env.example', 'README.md'], 'checklist' => $common],
            ['code' => 'iot', 'name' => 'Robotik / IoT', 'deployable' => false, 'checklist' => $common],
            ['code' => 'llm', 'name' => 'LLM / Chatbot', 'deployable' => false, 'checklist' => $common],
        ];
        foreach ($tracks as $t) {
            Track::updateOrCreate(['code' => $t['code']], $t + ['result_card_schema' => $t['result_card_schema'] ?? $card(['masalah_solusi' => 'Masalah & solusi', 'keterbatasan' => 'Keterbatasan', 'kalimat_sales' => 'Kalimat untuk sales'])]);
        }

        User::firstOrCreate(['email' => 'admin@fasinova.test'], ['name' => 'Admin', 'password' => 'password', 'role' => 'admin']);

        // Data awal fakultas, prodi, dan pengguna (kata sandi default: "password", wajib diganti).
        $fik = Faculty::firstOrCreate(['code' => 'FIK'], ['name' => 'Fakultas Ilmu Komputer']);
        $progs = [];
        foreach (['TI' => 'Teknik Informatika', 'SI' => 'Sistem Informasi', 'MIK' => 'Magister Ilmu Komputer', 'PJJ' => 'PJJ'] as $code => $name) {
            $progs[$code] = StudyProgram::firstOrCreate(['code' => $code], ['faculty_id' => $fik->id, 'name' => $name]);
        }
        $people = [
            ['riya@esaunggul.ac.id', 'Riya', 'dosen', 'TI'], ['hani@esaunggul.ac.id', 'Hani', 'dosen', 'SI'],
            ['gerry@esaunggul.ac.id', 'Gerry', 'dosen', null], ['habib@esaunggul.ac.id', 'Habib', 'dosen', 'MIK'],
            ['arief@esaunggul.ac.id', 'Arief', 'dosen', 'PJJ'], ['jefry@esaunggul.ac.id', 'Jefry', 'dosen', null],
            ['thifaal@student.esaunggul.ac.id', 'Thifaal', 'mahasiswa', 'SI'],
        ];
        foreach ($people as [$email, $name, $role, $prog]) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'role' => $role,
                'study_program_id' => $prog ? $progs[$prog]->id : null]);
            if ($prog && $role === 'dosen') $progs[$prog]->update(['head_id' => $u->id]); // kaprodi
        }
    }
}
