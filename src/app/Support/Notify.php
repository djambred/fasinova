<?php

namespace App\Support;

use App\Models\Innovation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/** Notifikasi dalam aplikasi (lonceng di panel). Pengganti email: tanpa SMTP. */
class Notify
{
    /** @param iterable<User|null> $users */
    public static function innovation(iterable $users, Innovation $i, string $title, ?string $body = null, string $status = 'info', ?string $url = null): void
    {
        foreach (collect($users)->filter()->unique('id') as $u) {
            Notification::make()->title($title)->body($body ?? $i->title)->status($status)
                ->actions([Action::make('buka')->label('Buka')->url($url ?? self::urlFor($u, $i))->markAsRead()])
                ->sendToDatabase($u);
        }
    }

    public static function users(iterable $users, string $title, string $body, string $url, string $status = 'info'): void
    {
        foreach (collect($users)->filter()->unique('id') as $u) {
            Notification::make()->title($title)->body($body)->status($status)
                ->actions([Action::make('buka')->label('Buka')->url($url)->markAsRead()])->sendToDatabase($u);
        }
    }

    /** Ketua + anggota tim (anggota dicocokkan lewat NIM). */
    public static function team(Innovation $i): Collection
    {
        $nims = collect($i->members)->pluck('nim')->filter();
        return User::where('id', $i->owner_id)->when($nims->isNotEmpty(), fn ($q) => $q->orWhereIn('nim', $nims))->get();
    }

    /** PIC peminatan karya; bila belum ada PIC, admin. */
    public static function pics(Innovation $i): Collection
    {
        $pics = $i->track->pics()->get();
        return $pics->isNotEmpty() ? $pics : User::where('role', 'admin')->get();
    }

    public static function admins(): Collection { return User::where('role', 'admin')->get(); }

    private static function urlFor(User $u, Innovation $i): string
    {
        return match ($u->role) {
            'mahasiswa' => in_array($i->status, ['draft', 'needs_revision']) ? "/app/innovations/{$i->id}/edit" : '/app/innovations',
            'dosen' => '/dosen/bimbingan',
            default => '/admin/innovations',
        };
    }
}
