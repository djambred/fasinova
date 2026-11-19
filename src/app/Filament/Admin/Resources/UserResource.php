<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
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

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|UnitEnum|null $navigationGroup = 'Master data';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $pluralModelLabel = 'Pengguna';
    protected static ?string $navigationLabel = 'Pengguna';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;


    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('nim')->label('NIM')->unique(ignoreRecord: true)->visible(fn ($get) => $get('role') === 'mahasiswa')
                ->required(fn ($get) => $get('role') === 'mahasiswa'),
            TextInput::make('kode_dosen')->label('Kode Dosen')->unique(ignoreRecord: true)->visible(fn ($get) => in_array($get('role'), ['dosen', 'pic']))
                ->required(fn ($get) => $get('role') === 'dosen'),
            Select::make('study_program_id')->label('Program studi')->relationship('program', 'name')->searchable()->preload(),
            Select::make('role')->label('Peran')->live()->options(['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen (pembimbing)', 'pic' => 'PIC', 'admin' => 'Admin'])->required(),
            Select::make('tracksAsPic')->label('PIC untuk peminatan')->relationship('tracksAsPic', 'name')->multiple()->preload(),
            TextInput::make('password')->label('Kata sandi')->password()->revealable()->default('password')
                ->dehydrated(fn ($s) => filled($s))->required(fn (string $operation) => $operation === 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nama')->searchable(), TextColumn::make('email')->searchable(),
            TextColumn::make('nim')->label('NIM / Kode Dosen')->state(fn (User $u) => $u->nim ?? $u->kode_dosen)->searchable(['nim', 'kode_dosen']), TextColumn::make('role')->label('Peran')->badge()->color(fn ($state) => match ($state) {
                'admin' => 'danger', 'pic' => 'warning', 'dosen' => 'info', 'mahasiswa' => 'success', default => 'gray',
            }),
            TextColumn::make('jabatan')->label('Jabatan')->state(fn (User $u) => $u->jabatan())->badge()->color('warning'),
            TextColumn::make('program.name')->label('Prodi'),
        ])->filters([\Filament\Tables\Filters\SelectFilter::make('role')->options(['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen', 'pic' => 'PIC', 'admin' => 'Admin']),
            \Filament\Tables\Filters\SelectFilter::make('study_program_id')->label('Prodi')->relationship('program', 'name')])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array { return ['index' => ListUsers::route('/')]; }
}
