<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\Inquiry;
use BackedEnum;
use UnitEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InquiryResource extends Resource
{
    protected static ?string $model = Inquiry::class;
    protected static string|UnitEnum|null $navigationGroup = 'Pemeriksaan';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Inquiry';
    protected static ?string $pluralModelLabel = 'Inquiry kolaborasi';
    protected static ?string $navigationLabel = 'Inquiry kolaborasi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->options(['new' => 'Baru', 'in_progress' => 'Diproses', 'done' => 'Selesai'])->required(),
            Select::make('assignee_id')->label('Penanggung jawab')->relationship('assignee', 'name'),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->dateTime(), TextColumn::make('innovation.title')->label('Karya'), TextColumn::make('name'),
            TextColumn::make('email'), TextColumn::make('organization'), TextColumn::make('message')->limit(60)->tooltip(fn ($record) => $record->message),
            TextColumn::make('status')->badge(), TextColumn::make('assignee.name'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListInquiries::route('/')]; }
}
