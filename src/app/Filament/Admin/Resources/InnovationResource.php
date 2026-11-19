<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\InnovationResource\Pages\ListInnovations;
use App\Jobs\RunnerJob;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Innovation;
use App\Models\Review;
use App\Services\RepoValidator;
use BackedEnum;
use UnitEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class InnovationResource extends Resource
{
    protected static ?string $model = Innovation::class;
    protected static string|UnitEnum|null $navigationGroup = 'Pemeriksaan';
    protected static ?int $navigationSort = 1;
    protected static ?string $pluralModelLabel = 'Karya';
    protected static ?string $navigationLabel = 'Antrean pemeriksaan';
    protected static ?string $modelLabel = 'Karya';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    // PIC-01: PIC hanya melihat peminatannya.
    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery()->with('track', 'owner', 'deployment', 'repository', 'resultCard');
        $u = auth()->user();
        return $u->isAdmin() ? $q : $q->whereIn('track_id', $u->picTrackIds());
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('title')->searchable()->wrap(),
            TextColumn::make('track.name')->label('Peminatan'),
            TextColumn::make('owner.name')->label('Ketua'),
            TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                'submitted' => 'warning', 'needs_revision' => 'danger', 'approved' => 'info', 'published' => 'success', default => 'gray',
            })->formatStateUsing(fn ($state, Innovation $record) => $record->isOverdue() ? 'terlambat' : $state),
            TextColumn::make('revision_due_at')->label('Tenggat')->dateTime('d M Y'),
            TextColumn::make('deployment.status')->label('Deploy')->badge(),
            IconColumn::make('published_at')->label('Terbit')->boolean(),
        ])->filters([
            SelectFilter::make('status')->options(['submitted' => 'Dikirim', 'needs_revision' => 'Perlu perbaikan', 'approved' => 'Disetujui', 'published' => 'Terbit', 'rejected' => 'Ditolak']),
            SelectFilter::make('track_id')->label('Peminatan')->relationship('track', 'name'),
        ])->recordActions([
            self::reviewAction(), self::safeAction(), self::deployAction(), self::publishAction(),
        ]);
    }

    private static function reviewAction(): Action
    {
        return Action::make('review')->authorize('review')->label('Periksa')->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (Innovation $record) => in_array($record->status, ['submitted', 'needs_revision']))
            ->modalWidth('4xl')
            ->schema(function (Innovation $record) {
                $report = $record->repository?->validation_report;
                $items = collect($report['items'] ?? [])->map(fn ($i) => Text::make(($i['ok'] ? '✅ ' : '❌ ').$i['label'].($i['detail'] && ! $i['ok'] ? " — {$i['detail']}" : '')));
                $warn = collect($record->resultCard?->claim_warnings ?? [])->map(fn ($w) => Text::make("⚠️ $w"));
                $fields = [Section::make('Hasil validasi otomatis')->schema($items->all() ?: [Text::make('Belum divalidasi.')])->collapsible()];
                if ($warn->isNotEmpty()) $fields[] = Section::make('Peringatan klaim')->schema($warn->all());
                foreach ($record->track->checklist ?? [] as $n => $label) {
                    $fields[] = Section::make($label)->schema([
                        Select::make("s_$n")->label('Hasil')->options(['ok' => 'Lulus', 'fix' => 'Perbaiki'])->default('ok')->required(),
                        TextInput::make("n_$n")->label('Catatan'),
                    ])->columns(2)->compact();
                }
                if ($record->track->code === 'cs' && $record->isDeployable()) {
                    $fields[] = Toggle::make('safe_ok')->label('Tandai service aman untuk online')->default((bool) $record->safe_service_ok)
                        ->helperText('Hanya dashboard replay, demo kripto, kuis awareness. Tanpa tanda ini karya Cyber Security tidak di-deploy otomatis.');
                }
                return [...$fields,
                    Select::make('decision')->label('Keputusan')->options(['approve' => 'Setujui (kunci SHA, terbit & deploy otomatis)', 'return' => 'Kembalikan (7 hari)', 'reject' => 'Tolak'])->required(),
                    Textarea::make('note')->label('Catatan umum')];
            })
            ->action(function (Innovation $record, array $data) {
                $result = collect($record->track->checklist ?? [])->map(fn ($label, $n) => ['item' => $label, 'status' => $data["s_$n"], 'note' => $data["n_$n"] ?? null])->all();
                $decision = $data['decision'];

                if ($decision === 'approve') {
                    $fail = collect($result)->contains('status', 'fix') ? 'Masih ada item "Perbaiki".'
                        : (! $record->supervisor_approved_at ? 'Pembimbing belum menyetujui.' : null);
                    if (! $fail) { // validasi ulang agar SHA yang dikunci adalah SHA tag saat ini
                        (new RepoValidator)->validate($record->fresh('repository', 'track'));
                        $rep = $record->repository->fresh()->validation_report;
                        $fail = empty($rep['ok']) ? 'Validasi repo gagal.' : null;
                    }
                    if ($fail) { Notification::make()->title($fail)->danger()->send(); return; }
                    $record->repository->update(['locked_commit_sha' => $rep['tag_sha']]);
                    if ($record->track->code === 'cs') $record->safe_service_ok = (bool) ($data['safe_ok'] ?? false);
                    // PIC cukup memvalidasi checklist; sisanya otomatis: terbit (bila formulir persetujuan ada) lalu deploy.
                    $record->fill(['status' => $record->consent_ok ? 'published' : 'approved', 'revision_due_at' => null,
                        'published_at' => $record->consent_ok ? now() : null])->save();
                    $deployQueued = self::autoDeploy($record);
                } elseif ($decision === 'return') {
                    $record->update(['status' => 'needs_revision', 'revision_due_at' => now()->addDays(config('fasinova.revision_days'))]);
                } else {
                    $record->update(['status' => 'rejected']);
                }
                Review::create(['innovation_id' => $record->id, 'reviewer_id' => auth()->id(), 'checklist_result' => $result, 'decision' => $decision, 'note' => $data['note'] ?? null]);
                AuditLog::record("review.$decision", $record->id);
                $team = \App\Support\Notify::team($record);
                $sup = $record->supervisor;
                match ($decision) {
                    'approve' => \App\Support\Notify::innovation([...$team, $sup], $record, 'Karya disetujui PIC',
                        $record->title.($record->status === 'published' ? ' — terbit di etalase' : '').($deployQueued ?? false ? '; demo sedang di-deploy otomatis' : ''), 'success'),
                    'return' => \App\Support\Notify::innovation([...$team, $sup], $record, 'Karya dikembalikan untuk perbaikan',
                        'Perbaiki sebelum '.$record->fresh()->revision_due_at->format('d M Y').': '.$record->title, 'danger'),
                    default => \App\Support\Notify::innovation([...$team, $sup], $record, 'Karya ditolak', $record->title, 'danger'),
                };
                Notification::make()->title('Keputusan tersimpan')->success()->send();
            });
    }

    /** Setelah validasi PIC: worker langsung deploy. Cyber Security hanya bila service ditandai aman. */
    private static function autoDeploy(Innovation $record): bool
    {
        $d = $record->deployment;
        if (! $record->isDeployable() || ! $d || blank($d->stack)
            || ($record->track->code === 'cs' && ! $record->safe_service_ok)) {
            return false;
        }
        $d->update(['status' => 'queued', 'triggered_by' => auth()->id()]);
        $d->logs()->delete();
        RunnerJob::dispatch($d->id, 'deploy');
        AuditLog::record('deploy.auto', $record->id, ['subdomain' => $d->subdomain, 'sha' => $record->repository->locked_commit_sha]);
        return true;
    }

    // PIC-05
    private static function safeAction(): Action
    {
        return Action::make('safe')->authorize('markSafe')->icon('heroicon-o-shield-check')
            ->label(fn (Innovation $record) => $record->safe_service_ok ? 'Cabut tanda aman' : 'Tandai service aman')
            ->visible(fn (Innovation $record) => $record->track->code === 'cs' && in_array($record->status, ['approved', 'published']))
            ->requiresConfirmation()->modalDescription('Hanya service aman (dashboard replay, demo kripto, kuis awareness) yang boleh online. Lab rentan dan CTF tidak pernah.')
            ->action(function (Innovation $record) {
                $record->update(['safe_service_ok' => ! $record->safe_service_ok]);
                AuditLog::record('cs.safe_flag', $record->id, ['value' => $record->safe_service_ok]);
            });
    }

    // PIC-04
    private static function deployAction(): Action
    {
        return Action::make('deploy')->authorize('deploy')->label(fn (Innovation $record) => $record->deployment?->status === 'live' ? 'Redeploy' : 'Deploy')->icon('heroicon-o-rocket-launch')
            ->visible(fn (Innovation $record) => $record->isDeployable() && in_array($record->status, ['approved', 'published']))
            ->disabled(fn (Innovation $record) => ($record->track->code === 'cs' && ! $record->safe_service_ok)
                || in_array($record->deployment?->status, ['queued', 'cloning', 'scanning', 'building', 'starting', 'health_check']))
            ->fillForm(fn (Innovation $record) => ['subdomain' => $record->deployment->subdomain, 'public_service' => $record->deployment->public_service,
                'internal_port' => $record->deployment->internal_port ?: $record->track->default_port, 'health_path' => $record->deployment->health_path])
            ->schema(fn (Innovation $record) => [
                TextInput::make('subdomain')->required()->regex('/^[a-z0-9-]{3,40}$/')
                    ->rules([Rule::notIn(config('fasinova.reserved_subdomains')), Rule::unique('deployments', 'subdomain')->ignore($record->deployment->id)])
                    ->suffix('.'.config('fasinova.domain')),
                TextInput::make('public_service')->label('Service publik')->helperText('Kosong = service pertama'),
                TextInput::make('internal_port')->numeric()->required(),
                TextInput::make('health_path')->required(),
            ])
            ->action(function (Innovation $record, array $data) {
                $d = $record->deployment;
                $d->update($data + ['status' => 'queued', 'triggered_by' => auth()->id()]);
                $d->logs()->delete();
                RunnerJob::dispatch($d->id, 'deploy');
                AuditLog::record('deploy', $record->id, ['subdomain' => $d->subdomain, 'sha' => $record->repository->locked_commit_sha]);
                Notification::make()->title('Deploy masuk antrean')->success()->send();
            });
    }

    // PIC-06: terbit terpisah dari deploy
    private static function publishAction(): Action
    {
        return Action::make('publish')->authorize('publish')->icon('heroicon-o-globe-alt')
            ->label(fn (Innovation $record) => $record->status === 'published' ? 'Tarik dari etalase' : 'Terbitkan')
            ->visible(fn (Innovation $record) => in_array($record->status, ['approved', 'published']))->requiresConfirmation()
            ->action(function (Innovation $record) {
                $pub = $record->status !== 'published';
                if ($pub && ! $record->consent_ok) { Notification::make()->title('Formulir persetujuan publikasi belum ada.')->danger()->send(); return; }
                $record->update($pub ? ['status' => 'published', 'published_at' => now()] : ['status' => 'approved', 'published_at' => null]);
                AuditLog::record($pub ? 'publish' : 'unpublish', $record->id);
                if ($pub) \App\Support\Notify::innovation([...\App\Support\Notify::team($record), $record->supervisor], $record, 'Karya terbit di etalase', $record->title, 'success');
            });
    }

    public static function getPages(): array { return ['index' => ListInnovations::route('/')]; }
}
