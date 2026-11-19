<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\InnovationResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class MyInnovations extends TableWidget
{
    protected static ?string $heading = 'Status karya & langkah berikutnya';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table->heading('Status karya & langkah berikutnya')->emptyStateHeading('Belum ada karya')->emptyStateDescription('Buat karya dari menu Karya saya.')->query(InnovationResource::getEloquentQuery()->latest('updated_at'))->paginated(false)->columns([
            TextColumn::make('title')->label('Judul')->wrap(),
            TextColumn::make('track.name')->label('Peminatan'),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($s, $record) => $record->isOverdue() ? 'terlambat' : $s),
            TextColumn::make('next')->label('Langkah berikutnya')->state(fn ($record) => match (true) {
                $record->status === 'draft' && ! $record->repository?->validation_report => 'Isi repo, env, materi, lalu validasi repo',
                $record->status === 'draft' && ! $record->supervisor_approved_at => 'Menunggu persetujuan pembimbing (dosen menyetujui di panelnya)',
                $record->status === 'draft' => 'Lengkapi lalu kirim paket',
                $record->status === 'needs_revision' => 'Perbaiki sesuai catatan PIC sebelum '.$record->revision_due_at?->format('d M Y'),
                $record->status === 'submitted' => 'Menunggu pemeriksaan PIC',
                $record->status === 'approved' => 'Menunggu PIC menerbitkan',
                $record->status === 'published' => $record->deployment?->url ? 'Demo: '.$record->deployment->url : 'Sudah terbit',
                default => '—',
            })->wrap(),
        ])->recordActions([
            Action::make('open')->label('Buka')->icon('heroicon-o-arrow-right')
                ->visible(fn ($record) => InnovationResource::canEdit($record))
                ->url(fn ($record) => InnovationResource::getUrl('edit', ['record' => $record])),
        ]);
    }
}
