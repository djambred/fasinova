<?php

namespace App\Http\Controllers;

use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home(Request $r)
    {
        $items = Innovation::with('track', 'deployment', 'owner')->where('status', 'published')
            // Sesuai placeholder "karya, teknologi, atau tim": judul/isi, ketua (nama/NIM), anggota, pembimbing, peminatan, stack.
            // % dan _ diketik pengguna dicari apa adanya, bukan wildcard (escape \ bawaan PostgreSQL).
            ->when(trim((string) $r->q), fn ($q, $v) => $q->where(function ($w) use ($v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $w->whereLike('title', $like)->orWhereLike('problem', $like)->orWhereLike('abstract', $like)
                    ->orWhereRaw('lower(cast(members as text)) like ?', [mb_strtolower($like)])   // jsonb: cast agar jalan di PostgreSQL & SQLite
                    ->orWhereHas('owner', fn ($u) => $u->whereLike('name', $like)->orWhereLike('nim', $like))
                    ->orWhereHas('supervisor', fn ($u) => $u->whereLike('name', $like))
                    ->orWhereHas('track', fn ($t) => $t->whereLike('name', $like))
                    ->orWhereHas('deployment', fn ($d) => $d->whereLike('stack', $like));
            }))
            ->when($r->track, fn ($q, $v) => $q->where('track_id', $v))
            ->when($r->prodi, fn ($q, $v) => $q->where('study_program', $v))
            ->when($r->tahun, fn ($q, $v) => $q->where('academic_period', $v))
            ->latest('published_at')->paginate(12)->withQueryString()->fragment('karya');

        $pub = Innovation::where('status', 'published');
        return view('site.home', [
            'items' => $items, 'tracks' => Track::all(),
            'prodis' => (clone $pub)->whereNotNull('study_program')->distinct()->pluck('study_program'),
            'tahuns' => (clone $pub)->whereNotNull('academic_period')->distinct()->pluck('academic_period'),
            'total' => (clone $pub)->count(),
            'live' => (clone $pub)->whereHas('deployment', fn ($q) => $q->where('status', 'live'))->count(),
        ]);
    }

    /** Dosen pembimbing (peran dosen/PIC) beserta jumlah karya terbit bimbingannya. Hanya data publik: nama, jabatan, prodi, peminatan. */
    private function lecturers()
    {
        return User::whereIn('role', ['dosen', 'pic'])->with('program', 'tracksAsPic')
            ->withExists(['headOf as is_kaprodi', 'deanOf as is_dekan'])   // jabatan tanpa 2 query per kartu
            ->withCount(['supervised as karya_count' => fn ($q) => $q->where('status', 'published')])
            ->addSelect(['mahasiswa_count' => Innovation::selectRaw('count(distinct owner_id)')->whereColumn('supervisor_id', 'users.id')->where('status', 'published')]);
    }

    public function lecturerIndex()
    {
        return view('site.lecturers', ['lecturers' => $this->lecturers()->orderByDesc('karya_count')->orderBy('name')->paginate(24)]);
    }

    public function lecturer(string $key)
    {
        $u = $this->lecturers()->findOrFail((int) $key);
        if ($key !== $u->profileKey()) return redirect($u->profileUrl(), 301);   // URL kanonik /pembimbing/{id}-{nama}
        $items = Innovation::with('track', 'deployment', 'owner')->where('status', 'published')->where('supervisor_id', $u->id)->latest('published_at')->paginate(12)->fragment('karya');
        return view('site.lecturer', ['u' => $u, 'items' => $items]);
    }

    public function show(string $slug)
    {
        $i = Innovation::with('track', 'owner', 'supervisor', 'resultCard', 'deployment')->where('status', 'published')->where('slug', $slug)->firstOrFail();
        return view('site.show', ['i' => $i]);
    }

    public function inquire(Request $r, string $slug)
    {
        $i = Innovation::where('status', 'published')->where('slug', $slug)->firstOrFail();
        $d = $r->validate(['name' => 'required|max:100', 'email' => 'required|email', 'organization' => 'nullable|max:150',
            'message' => 'required|max:2000', 'pdp' => 'accepted']);

        \App\Support\Turnstile::verify($r);
        $inq = $i->inquiries()->create(collect($d)->except('pdp')->all());
        \App\Support\Notify::users(\App\Support\Notify::admins(), 'Inquiry kolaborasi baru', "{$inq->name} — {$i->title}", '/admin/inquiries', 'info');
        // Tim karya & pembimbing ikut tahu (lihat & balas dari panel masing-masing); admin tetap mengelola statusnya.
        \App\Support\Notify::users(\App\Support\Notify::team($i), 'Ada mitra tertarik dengan karya Anda', "{$inq->name} — {$i->title}", '/app/inquiries', 'success');
        \App\Support\Notify::users([$i->supervisor], 'Ada mitra tertarik dengan karya bimbingan Anda', "{$inq->name} — {$i->title}", '/dosen/inquiries', 'success');
        return back()->with('ok', 'Terima kasih, pesan Anda terkirim. Kami membalas dalam 5 hari kerja.');
    }

}
