<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    // Peran disimpan sebagai satu kolom (satu peran per pengguna cukup untuk v1.1).
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => in_array($this->role, ['pic', 'dosen', 'admin']), // dosen = PIC bimbingan/lingkupnya
            'app' => in_array($this->role, ['mahasiswa', 'admin']),
            'dosen' => in_array($this->role, ['dosen', 'pic', 'admin']),   // PIC adalah dosen: tetap punya dasbor dosen
            default => false,
        };
    }

    protected static function booted(): void
    {
        // study_program (teks) dipakai karya & etalase; selalu ikut program studi terpilih.
        static::saving(function (self $u) {
            if ($u->study_program_id) $u->study_program = StudyProgram::find($u->study_program_id)?->name;
        });
    }

    public function program() { return $this->belongsTo(StudyProgram::class, 'study_program_id'); }

    /** Jabatan struktural: Dekan / Kaprodi (dari pengaturan Fakultas & Program Studi). */
    public function jabatan(): ?string
    {
        return collect([
            Faculty::where('dean_id', $this->id)->exists() ? 'Dekan' : null,
            StudyProgram::where('head_id', $this->id)->exists() ? 'Kaprodi' : null,
        ])->filter()->implode(', ') ?: null;
    }

    /** Prodi yang dipimpin (kaprodi) + semua prodi di fakultas yang dipimpin (dekan). */
    public function scopeProgramIds(): array
    {
        $ids = StudyProgram::where('head_id', $this->id)->pluck('id');
        $fac = Faculty::where('dean_id', $this->id)->pluck('id');
        return $ids->merge(StudyProgram::whereIn('faculty_id', $fac)->pluck('id'))->unique()->values()->all();
    }

    /** Halaman tujuan setelah login, menurut peran. */
    public function homeUrl(): string
    {
        return match ($this->role) {
            'admin', 'pic' => '/admin',
            'dosen' => '/dosen',
            default => '/app',
        };
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isPic(): bool { return in_array($this->role, ['pic', 'dosen']); }

    public function tracksAsPic() { return $this->belongsToMany(Track::class, 'track_pics'); }

    /** Peminatan yang boleh dilihat PIC; admin melihat semua. */
    public function picTrackIds(): array
    {
        return $this->tracksAsPic()->pluck('tracks.id')->all();
    }
}
