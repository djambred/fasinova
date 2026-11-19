<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TrackResource\Pages\ListTracks;
use App\Models\Track;
use BackedEnum;
use UnitEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** ADM-01: SOP baru = data, bukan kode. */
class TrackResource extends Resource
{
    protected static ?string $model = Track::class;
    protected static string|UnitEnum|null $navigationGroup = 'Master data';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Peminatan';
    protected static ?string $pluralModelLabel = 'Peminatan (SOP)';
    protected static ?string $navigationLabel = 'Peminatan (SOP)';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true), TextInput::make('name')->required(),
            TextInput::make('repo_prefix'), TextInput::make('default_port')->numeric(), TextInput::make('sop_url')->url(),
            Toggle::make('deployable')->label('Boleh deploy'),
            TagsInput::make('required_files')->helperText('Pisahkan alternatif dengan | (mis. Dockerfile|docker-compose.yml)'),
            TagsInput::make('checklist')->label('Checklist PIC'),
            TagsInput::make('forbidden_images')->helperText('Pola nama image, mis. kalilinux/*'),
            Repeater::make('result_card_schema')->label('Field kartu hasil')->schema([
                TextInput::make('key')->required(), TextInput::make('label')->required(), Toggle::make('required')->default(true),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('code'), TextColumn::make('name'), TextColumn::make('repo_prefix'), IconColumn::make('deployable')->boolean()])
            ->recordActions([EditAction::make()])->headerActions([CreateAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListTracks::route('/')]; }
}
