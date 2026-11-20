<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DeploymentResource\Pages\ListDeployments;
use App\Jobs\RunnerJob;
use App\Models\AuditLog;
use App\Models\Deployment;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/** Modul Deploy gaya Portainer: daftar stack + aksi. Tanpa console/exec (PRD 6.1). */
class DeploymentResource extends Resource
{
    protected static ?string $model = Deployment::class;
    protected static string|UnitEnum|null $navigationGroup = 'Deploy';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Stack';
    protected static ?string $pluralModelLabel = 'Stack deploy';
    protected static ?string $navigationLabel = 'Deploy (stack)';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with('innovation.track');
        $u = auth()->user();
        return $q->whereHas('innovation', fn ($i) => $i->managedBy($u));
    }


    public static function table(Table $table): Table
    {
        return $table->poll('5s')->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', 'not_deployed'))
            ->columns([
                TextColumn::make('subdomain')->searchable(),
                TextColumn::make('innovation.title')->label('Karya')->limit(40),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'live' => 'success', 'failed' => 'danger', 'stopped' => 'gray', default => 'warning',
                })->description(fn (Deployment $record) => $record->needs_redeploy ? 'perlu redeploy' : null),
                TextColumn::make('commit_sha')->label('Commit')->formatStateUsing(fn ($state) => substr((string) $state, 0, 7)),
                TextColumn::make('host_port')->label('Port'),
                TextColumn::make('db_engine')->label('DB')->badge()->formatStateUsing(fn ($state) => \App\Services\DbProvisioner::ENGINES[$state] ?? '—'),
                TextColumn::make('last_health_at')->label('Health')->since(),
                TextColumn::make('finished_at')->label('Deploy terakhir')->since(),
            ])->filters([
                SelectFilter::make('status')->options(collect(['live', 'stopped', 'failed', 'building', 'queued'])->mapWithKeys(fn ($s) => [$s => $s])->all()),
                SelectFilter::make('peminatan')->label('Peminatan')->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('innovation', fn ($i) => $i->where('track_id', $data['value'])) : $query)
                    ->options(\App\Models\Track::pluck('name', 'id')),
            ])->recordActions([
                self::job('redeploy', 'Redeploy', 'deploy', 'heroicon-o-arrow-path'),
                ActionGroup::make([
                    self::job('start', 'Start', 'start', 'heroicon-o-play'),
                    self::job('stop', 'Stop', 'stop', 'heroicon-o-stop'),
                    self::job('restart', 'Restart', 'restart', 'heroicon-o-arrow-uturn-left'),
                    self::job('remove', 'Hapus', 'remove', 'heroicon-o-trash', true),
                    self::logsAction(), self::quotaAction(),
                ]),
            ]);
    }

    private static function job(string $name, string $label, string $action, string $icon, bool $danger = false): Action
    {
        return Action::make($name)->authorize('update')->label($label)->icon($icon)->color($danger ? 'danger' : 'gray')->requiresConfirmation()
            // Deploy hanya untuk karya yang sudah disetujui PIC (SHA terkunci); sebelum itu aksi ini tidak ada artinya dan hanya membuat status "gagal".
            ->visible(fn (?Deployment $record) => $action === 'remove' || filled($record?->innovation?->repository?->locked_commit_sha))
            ->disabled(fn (?Deployment $record) => $action === 'deploy' && (bool) $record?->isBusy())
            ->action(function (Deployment $record) use ($action, $name) {
                if ($action === 'deploy') $record->update(['status' => 'queued', 'triggered_by' => auth()->id()]);
                RunnerJob::dispatch($record->id, $action);
                AuditLog::record("deploy.$name", $record->innovation_id, ['subdomain' => $record->subdomain]);
                Notification::make()->title("$name dijadwalkan")->success()->send();
            });
    }

    private static function logsAction(): Action
    {
        return Action::make('logs')->authorize('view')->label('Log')->icon('heroicon-o-document-text')->modalSubmitAction(false)->modalWidth('5xl')
            ->modalContent(fn (Deployment $record): View => view('filament.deployment-logs', ['logs' => $record->logs()->latest('id')->limit(500)->get()->reverse()]));
    }

    private static function quotaAction(): Action
    {
        return Action::make('quota')->authorize('update')->label('Kuota')->icon('heroicon-o-adjustments-horizontal')
            ->fillForm(fn (Deployment $record) => $record->only('cpu_limit', 'mem_limit_mb', 'allowed_caps'))
            ->schema([
                TextInput::make('cpu_limit')->numeric()->minValue(0.1)->maxValue(4)->required(),
                TextInput::make('mem_limit_mb')->numeric()->minValue(128)->maxValue(4096)->required(),
                CheckboxList::make('allowed_caps')->label('Kapabilitas tambahan (dasar selalu aktif)')->options(array_combine($extra = array_values(array_diff(config('fasinova.allowed_caps'), config('fasinova.base_caps'))), $extra)),
            ])->action(function (Deployment $record, array $data) {
                $record->update($data + ['needs_redeploy' => true]);
                AuditLog::record('deploy.quota', $record->innovation_id, $data);
            });
    }

    public static function getPages(): array { return ['index' => ListDeployments::route('/')]; }
}
