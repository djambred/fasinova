<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\FacultyResource\Pages\ListFaculties;
use App\Models\Faculty;
use App\Models\User;
use BackedEnum;
use UnitEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;
    protected static string|UnitEnum|null $navigationGroup = 'Master data';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Fakultas';
    protected static ?string $pluralModelLabel = 'Fakultas';
    protected static ?string $navigationLabel = 'Fakultas';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama fakultas')->required(),
            Select::make('dean_id')->label('Dekan')->options(fn () => User::where('role', '!=', 'mahasiswa')->pluck('name', 'id'))->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code'), TextColumn::make('name')->searchable(), TextColumn::make('dean.name')->label('Dekan'),
            TextColumn::make('programs_count')->counts('programs')->label('Jumlah prodi'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListFaculties::route('/')]; }
}
