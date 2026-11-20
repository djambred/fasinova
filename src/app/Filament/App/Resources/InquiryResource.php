<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\Inquiry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inquiry kolaborasi untuk karya yang terlihat di panel ini (mahasiswa: karya sendiri). Baca saja + balas lewat email;
 * status & penanggung jawab tetap dikelola admin (ADM-05). Versi dosen mewarisi kelas ini dan hanya mengganti cakupan karyanya.
 */
class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Inquiry';
    protected static ?string $pluralModelLabel = 'Inquiry kolaborasi';
    protected static ?string $navigationLabel = 'Inquiry kolaborasi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    /** Karya yang inquiry-nya boleh dilihat di panel ini. */
    protected static function innovations(): Builder
    {
        return InnovationResource::getEloquentQuery();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('innovation')->whereIn('innovation_id', static::innovations()->select('innovations.id'));
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('status', 'new')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Masuk')->since()->dateTimeTooltip(),
            TextColumn::make('innovation.title')->label('Karya')->wrap(),
            TextColumn::make('name')->label('Nama')->description(fn (Inquiry $record) => $record->organization),
            TextColumn::make('email')->copyable(),
            TextColumn::make('message')->label('Pesan')->limit(80)->wrap()->tooltip(fn (Inquiry $record) => $record->message),
            TextColumn::make('status')->badge()
                ->formatStateUsing(fn ($state) => ['new' => 'Baru', 'in_progress' => 'Diproses', 'done' => 'Selesai'][$state] ?? $state)
                ->color(fn ($state) => ['new' => 'warning', 'in_progress' => 'info', 'done' => 'success'][$state] ?? 'gray'),
        ])->recordActions([
            Action::make('balas')->label('Balas')->icon(Heroicon::OutlinedPaperAirplane)->openUrlInNewTab()
                ->url(fn (Inquiry $record) => 'mailto:'.$record->email.'?subject='.rawurlencode('Kolaborasi: '.$record->innovation?->title)),
        ])->emptyStateHeading('Belum ada inquiry')
            ->emptyStateDescription('Pesan dari mitra lewat form "Tertarik berkolaborasi?" di halaman karya akan muncul di sini.');
    }

    public static function getPages(): array { return ['index' => ListInquiries::route('/')]; }
}
