<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistem';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Log';
    protected static ?string $pluralModelLabel = 'Log audit';
    protected static ?string $navigationLabel = 'Log audit';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;


    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->dateTime(), TextColumn::make('user.name'), TextColumn::make('action')->searchable(),
            TextColumn::make('subject_id'), TextColumn::make('meta')->formatStateUsing(fn ($state) => json_encode($state))->limit(80),
        ]);
    }

    public static function getPages(): array { return ['index' => ListAuditLogs::route('/')]; }
}
