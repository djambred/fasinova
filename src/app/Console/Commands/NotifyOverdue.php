<?php

namespace App\Console\Commands;

use App\Models\Innovation;
use App\Support\Notify;
use Illuminate\Console\Command;

class NotifyOverdue extends Command
{
    protected $signature = 'fasinova:overdue';
    protected $description = 'Beri tahu tim, PIC, dan pembimbing saat tenggat perbaikan 7 hari lewat (harian)';

    public function handle(): void
    {
        Innovation::with('track.pics', 'supervisor', 'owner')->where('status', 'needs_revision')
            ->whereBetween('revision_due_at', [now()->subDay(), now()])->each(function (Innovation $i) {
                Notify::innovation([...Notify::team($i), ...Notify::pics($i), $i->supervisor], $i, 'Perbaikan karya terlambat', $i->title, 'danger');
            });
    }
}
