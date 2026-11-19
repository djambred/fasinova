<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\StudyProgramResource\Pages\ListStudyPrograms;
use App\Models\StudyProgram;
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

class StudyProgramResource extends Resource
{
    protected static ?string $model = StudyProgram::class;
    protected static string|UnitEnum|null $navigationGroup = 'Master data';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Program studi';
    protected static ?string $pluralModelLabel = 'Program studi';
    protected static ?string $navigationLabel = 'Program studi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('faculty_id')->label('Fakultas')->relationship('faculty', 'name')->required()->preload(),
            TextInput::make('code')->label('Kode')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama program studi')->required(),
            Select::make('head_id')->label('Kaprodi')->options(fn () => User::where('role', '!=', 'mahasiswa')->pluck('name', 'id'))->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code'), TextColumn::make('name')->searchable(), TextColumn::make('faculty.name')->label('Fakultas'),
            TextColumn::make('head.name')->label('Kaprodi'), TextColumn::make('users_count')->counts('users')->label('Pengguna'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListStudyPrograms::route('/')]; }
}
