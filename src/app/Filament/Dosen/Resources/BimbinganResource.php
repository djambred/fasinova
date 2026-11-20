<?php

namespace App\Filament\Dosen\Resources;

use App\Filament\Dosen\Resources\BimbinganResource\Pages\ListBimbingan;
use App\Models\AuditLog;
use App\Models\Innovation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Karya bimbingan; kaprodi/dekan juga melihat karya prodi/fakultasnya (hanya lihat). */
class BimbinganResource extends Resource
{
    protected static ?string $model = Innovation::class;
    protected static ?string $slug = 'bimbingan';
    protected static ?string $modelLabel = 'Karya';
    protected static ?string $pluralModelLabel = 'Karya bimbingan';
    protected static ?string $navigationLabel = 'Karya bimbingan';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getEloquentQuery(): Builder
    {
        $u = auth()->user();
        $programs = $u->scopeProgramIds();
        return parent::getEloquentQuery()->with('track', 'owner')->where(fn ($q) => $q->where('supervisor_id', $u->id)
            ->when($programs, fn ($q) => $q->orWhereIn('study_program_id', $programs)));
    }

    // Tampilan khusus baca; model sama dengan panel lain, jadi create/edit/hapus dikunci di sini.
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('title')->label('Judul')->searchable()->wrap(),
            TextColumn::make('owner.name')->label('Ketua')->searchable(),
            TextColumn::make('owner.nim')->label('NIM')->searchable(),
            TextColumn::make('supervisor.name')->label('Pembimbing')->searchable()->toggleable(),
            TextColumn::make('track.name')->label('Peminatan'),
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state, Innovation $record) => $record->statusLabel())->color(fn ($state, Innovation $record) => $record->statusColor()),
            IconColumn::make('supervisor_approved_at')->label('Disetujui saya')->boolean()
                ->state(fn (Innovation $record) => $record->supervisor_id === auth()->id() ? (bool) $record->supervisor_approved_at : null),
        ])->filters([
            SelectFilter::make('status')->options(['draft' => 'Draf', 'submitted' => 'Dikirim', 'needs_revision' => 'Perlu perbaikan', 'approved' => 'Disetujui', 'published' => 'Terbit']),
        ])->recordActions([
            Action::make('approve')->authorize('approveAsSupervisor')->label('Setujui')->icon('heroicon-o-hand-thumb-up')->color('success')->requiresConfirmation()
                ->modalDescription('Menyatakan Anda menyetujui karya ini untuk diperiksa PIC.')
                ->visible(fn (Innovation $record) => $record->supervisor_id === auth()->id() && ! $record->supervisor_approved_at)
                ->action(function (Innovation $record) {
                    $record->update(['supervisor_approved_at' => now()]);
                    AuditLog::record('supervisor.approve', $record->id);
                    \App\Support\Notify::innovation(\App\Support\Notify::team($record), $record, 'Pembimbing menyetujui karya', 'Anda dapat mengirim paket: '.$record->title, 'success');
                    Notification::make()->title('Persetujuan tercatat')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array { return ['index' => ListBimbingan::route('/')]; }
}
