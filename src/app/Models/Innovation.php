<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Innovation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'materials' => 'array', 'members' => 'array', 'consent_ok' => 'boolean',
            'safe_service_ok' => 'boolean', 'tos_accepted' => 'boolean', 'tos_accepted_at' => 'datetime', 'supervisor_approved_at' => 'datetime',
            'revision_due_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Teks prodi (etalase publik) selalu ikut prodi terpilih.
        // (jangan jadikan arrow function: nilai balik false dari event `saving` membatalkan penyimpanan secara diam-diam)
        static::saving(function (self $i): void {
            if ($i->study_program_id) $i->study_program = StudyProgram::find($i->study_program_id)?->name;
        });

        static::creating(function (self $i) {
            $i->slug ??= Str::slug($i->title);
            $base = $i->slug;
            for ($n = 2; static::where('slug', $i->slug)->exists(); $n++) {
                $i->slug = "$base-$n";
            }
        });

        // Baris anak selalu ada, agar form mahasiswa cukup memperbarui (hasOne relationship).
        static::created(function (self $i) {
            $sub = substr(str_pad($i->slug, 3, 'x'), 0, 40);
            for ($n = 2; Deployment::where('subdomain', $sub)->exists(); $n++) $sub = substr($sub, 0, 36).'-'.$n;
            $i->repository()->create(['url' => '']);
            $i->resultCard()->create(['data' => []]);
            $i->deployment()->create(['subdomain' => $sub, 'cpu_limit' => Setting::get('cpu_limit'), 'mem_limit_mb' => Setting::get('mem_limit_mb'),
                'internal_port' => $i->track->default_port]);
        });
    }

    public function track() { return $this->belongsTo(Track::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    /** Lingkup PIC: peminatan yang dipegang; dosen juga PIC bimbingannya + prodi/fakultas yang dipimpin (kaprodi/dekan). Admin: semua. */
    public function scopeManagedBy($q, User $u)
    {
        if ($u->isAdmin()) return $q;
        return $q->where(function ($w) use ($u) {
            $w->whereIn('track_id', $u->picTrackIds());
            if ($u->role === 'dosen') {
                $w->orWhere('supervisor_id', $u->id)
                    ->orWhereIn('study_program_id', $u->scopeProgramIds());
            }
        });
    }

    public function supervisor() { return $this->belongsTo(User::class, 'supervisor_id'); }
    public function repository() { return $this->hasOne(Repository::class); }
    public function envVariables() { return $this->hasMany(EnvVariable::class); }
    public function deployment() { return $this->hasOne(Deployment::class); }
    public function resultCard() { return $this->hasOne(ResultCard::class); }
    public function reviews() { return $this->hasMany(Review::class); }
    public function inquiries() { return $this->hasMany(Inquiry::class); }

    /** Alur deploy hanya untuk bentuk "web" pada peminatan yang boleh deploy. */
    public function isDeployable(): bool
    {
        return $this->form_type === 'web' && (bool) $this->track?->deployable;
    }

    public function formTypeLabel(): ?string
    {
        return config("fasinova.form_types.{$this->form_type}.label");
    }

    /** Pemilik atau anggota tim (dicocokkan lewat NIM). */
    public function isMember(User $u): bool
    {
        return $this->owner_id === $u->id || ($u->nim && collect($this->members)->contains('nim', $u->nim));
    }

    /** Terlambat: dikembalikan dan tenggat 7 hari lewat. */
    public function isOverdue(): bool
    {
        return $this->status === 'needs_revision' && $this->revision_due_at?->isPast();
    }

    public function demoUrl(): ?string
    {
        $d = $this->deployment;
        return $d && $d->status === 'live' ? $d->url : null;
    }
}
