<?php

namespace App\Filament\Admin\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use UnitEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** ADM-02: pengaturan deploy (nilai kosong = pakai .env). */
class Settings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $navigationLabel = 'Pengaturan deploy';
    protected static string|UnitEnum|null $navigationGroup = 'Sistem';
    protected static ?int $navigationSort = 1;
    protected ?string $heading = 'Pengaturan deploy';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected string $view = 'filament.settings';
    public ?array $data = [];

    private const KEYS = ['domain', 'port_range', 'cpu_limit', 'mem_limit_mb'];

    public static function canAccess(): bool { return \Illuminate\Support\Facades\Gate::allows('manage-settings'); }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('domain')->required(), TextInput::make('port_range')->required(),
            TextInput::make('cpu_limit')->label('Kuota CPU default')->numeric(), TextInput::make('mem_limit_mb')->label('Kuota RAM default (MB)')->numeric(),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $k => $v) {
            Setting::put($k, $v === null ? null : (string) $v);
        }
        AuditLog::record('settings.update', null, ['keys' => array_keys($this->data)]);
        Notification::make()->title('Tersimpan')->success()->send();
    }
}
