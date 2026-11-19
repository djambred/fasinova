<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\InnovationResource\Pages\CreateInnovation;
use App\Filament\App\Resources\InnovationResource\Pages\EditInnovation;
use App\Filament\App\Resources\InnovationResource\Pages\ListInnovations;
use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InnovationResource extends Resource
{
    protected static ?string $model = Innovation::class;
    protected static ?string $modelLabel = 'Karya';
    protected static ?string $navigationLabel = 'Karya saya';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    public const EDITABLE = ['draft', 'needs_revision'];

    // Mahasiswa hanya melihat karyanya (pemilik atau anggota tim).
    public static function getEloquentQuery(): Builder
    {
        $u = auth()->user();
        return parent::getEloquentQuery()->with('track', 'deployment', 'reviews')->where(fn ($q) => $q->where('owner_id', $u->id)
            ->when($u->nim, fn ($q) => $q->orWhereJsonContains('members', [['nim' => $u->nim]])));
    }

    public static function form(Schema $schema): Schema
    {
        // Alur deploy (stack/DB/repo/env/subdomain) hanya untuk bentuk "web" di peminatan yang boleh deploy.
        $deployable = fn (Get $get, ?Innovation $record, string $operation) => $operation === 'edit' && $get('form_type') === 'web' && (bool) $record?->track?->deployable;
        $formTypes = collect(config('fasinova.form_types'))->map(fn ($t) => $t['label'])->all();

        return $schema->components([
            // Langkah awal (buat karya): peminatan, judul. Sisanya dalam wizard setelah karya dibuat.
            Section::make('Karya baru')->visible(fn (string $operation) => $operation === 'create')->schema([
                Select::make('track_id')->label('Peminatan')->options(Track::pluck('name', 'id'))->required(),
                Select::make('form_type')->label('Bentuk karya')->options($formTypes)->required()
                    ->helperText('Alur deploy otomatis saat ini baru tersedia untuk "Aplikasi web". Bentuk lain diisi tanpa deploy.'),
                TextInput::make('title')->label('Judul karya')->required()->maxLength(120),
                Select::make('study_program_id')->label('Program studi')->options(\App\Models\StudyProgram::pluck('name', 'id'))->default(fn () => auth()->user()->study_program_id)
                    ->searchable()->required()->helperText('Kaprodi prodi ini dan dosen pembimbing menjadi PIC karya Anda.'),
                Select::make('supervisor_id')->label('Dosen pembimbing')->options(User::where('role', 'dosen')->pluck('name', 'id'))->searchable()->required(),
            ])->columns(2),

            Wizard::make([
                Step::make('Bentuk karya')->schema([
                    Select::make('form_type')->label('Bentuk karya')->options($formTypes)->required()->live()
                        ->helperText('Menentukan langkah yang tampil. Alur deploy otomatis (stack, database, GitHub, env, subdomain) baru tersedia untuk "Aplikasi web"; bentuk lain belum punya alur deploy dan hanya mengisi data karya.'),
                    Text::make(fn (Get $get) => $get('form_type') && $get('form_type') !== 'web' ? 'Bentuk ini belum memiliki alur deploy. Lanjutkan mengisi pembimbing, abstrak, poster, dan persetujuan publikasi.' : '')->columnSpanFull(),
                ]),

                Step::make('Stack & database')->visible($deployable)->schema([
                    Group::make()->relationship('deployment')->visible($deployable)->schema([
                        Select::make('stack')->label('Stack aplikasi')->options(collect(config('fasinova.stacks'))->map(fn ($s) => $s['label'])->all())
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) => $set('internal_port', config("fasinova.stacks.$state.port")))
                            ->helperText('Mengisi port internal otomatis; bisa Anda ubah.'),
                        Select::make('db_engine')->label('Database yang disediakan')->placeholder('Tidak pakai database')
                            ->options(\App\Services\DbProvisioner::ENGINES)
                            ->helperText('FASINOVA membuat database + user khusus karya Anda dan mengisi DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD otomatis saat deploy. Jangan isi variabel DB_* sendiri.'),
                        TextInput::make('internal_port')->label('Port internal')->numeric(),
                        TextInput::make('public_service')->label('Service yang ditampilkan publik')->helperText('Kosong = service pertama di docker-compose.yml'),
                        TextInput::make('health_path')->label('Path health check')->default('/')->dehydrateStateUsing(fn ($state) => filled($state) ? $state : '/'),
                    ])->columns(2)->columnSpanFull(),
                ]),

                Step::make('Repo GitHub')->visible($deployable)->schema([
                    Group::make()->relationship('repository')->visible($deployable)->schema([
                        TextInput::make('url')->label('Tautan repo (publik)')->url()->placeholder('https://github.com/<org-prodi>/ai-...'),
                        TextInput::make('tag')->label('Tag / branch (opsional)')->placeholder('mis. v1.0-showcase atau main')
                            ->helperText('Boleh tag, nama branch, atau commit. Kosongkan untuk memakai branch default; versi terkini dikunci saat PIC menyetujui.'),
                    ])->columns(2)->columnSpanFull(),
                    Group::make(fn (?Innovation $record) => collect($record?->repository?->validation_report['items'] ?? [])
                        ->map(fn ($i) => Text::make((($i['warn'] ?? false) ? '⚠️ ' : ($i['ok'] ? '✅ ' : '❌ ')).$i['label'].((! $i['ok'] || ($i['warn'] ?? false)) && $i['detail'] ? " — {$i['detail']}" : '')))->all())->columnSpanFull(),
                ]),

                Step::make('Variabel .env')->visible($deployable)
                    ->schema([
                        Text::make('Nilai rahasia disamarkan setelah disimpan dan tidak pernah ditampilkan ulang. Kosongkan nilai untuk mempertahankan yang lama.')->columnSpanFull(),
                        Repeater::make('envVariables')->relationship()->visible($deployable)->schema([
                            TextInput::make('key')->required()->regex('/^[A-Z_][A-Z0-9_]*$/i')->distinct(),
                            TextInput::make('value')->password(fn ($get) => (bool) $get('is_secret'))
                                ->placeholder(fn ($record) => $record?->is_secret ? '•••••• (tersimpan)' : null)
                                ->formatStateUsing(fn ($state, $record) => $record?->is_secret ? null : $state)
                                ->dehydrated(fn ($state) => filled($state)),
                            Toggle::make('is_secret')->label('Rahasia')->default(true)->live(),
                        ])->columns(3)->addActionLabel('Tambah variabel')->columnSpanFull()
                          ->mutateRelationshipDataBeforeSaveUsing(fn (array $d) => $d + ['updated_by' => auth()->id()])
                          ->mutateRelationshipDataBeforeCreateUsing(fn (array $d) => $d + ['updated_by' => auth()->id()]),
                    ]),

                Step::make('Subdomain')->visible($deployable)->schema([
                    Group::make()->relationship('deployment')->visible($deployable)->schema([
                        TextInput::make('subdomain')->label('Subdomain demo')->required()->suffix('.'.config('fasinova.domain'))
                            ->regex('/^[a-z0-9]([a-z0-9-]{1,38})[a-z0-9]$/')->validationMessages(['regex' => '3–40 karakter: huruf kecil, angka, tanda hubung (tidak di awal/akhir).'])
                            ->rules([
                                fn ($livewire) => function (string $attr, $value, \Closure $fail) use ($livewire) {
                                    $v = strtolower((string) $value);
                                    if (in_array($v, config('fasinova.reserved_subdomains'))) $fail('Subdomain ini dicadangkan sistem.');
                                    foreach (config('fasinova.forbidden_subdomain_words') as $w) if (str_contains($v, $w)) $fail('Subdomain mengandung kata yang tidak diizinkan.');
                                    if (\App\Models\Deployment::where('subdomain', $v)->where('innovation_id', '!=', $livewire->getRecord()?->getKey())->exists()) $fail('Subdomain sudah dipakai karya lain.');
                                },
                            ])
                            ->helperText('Alamat demo Anda: https://<subdomain>.'.config('fasinova.domain').'. Tidak bisa diubah setelah karya terbit.')
                            ->disabled(fn ($record) => $record && in_array($record->status, ['live', 'stopped'])),
                    ])->columnSpanFull(),
                ]),

                Step::make('Pembimbing & tim')->schema([
                    // Tidak wajib saat Simpan (galat di langkah yang sedang tidak tampil membuat Simpan gagal tanpa penjelasan); dicek saat Kirim paket.
                    Select::make('study_program_id')->label('Program studi')->options(\App\Models\StudyProgram::pluck('name', 'id'))->searchable()
                        ->afterStateHydrated(fn ($component, $state) => $state ?: $component->state(auth()->user()?->study_program_id))   // karya lama tanpa prodi: isi dari prodi mahasiswa
                        ->helperText('Kaprodi prodi ini dan dosen pembimbing menjadi PIC karya Anda.'),
                    Select::make('supervisor_id')->label('Dosen pembimbing')->options(User::where('role', 'dosen')->pluck('name', 'id'))->searchable()->required(),
                    TextInput::make('academic_period')->label('Periode TA (mis. 2026/2027)'),
                    Repeater::make('members')->label('Anggota tim (selain ketua)')->schema([
                        TextInput::make('nim')->required(), TextInput::make('name')->required(),
                    ])->columns(2)->columnSpanFull(),
                ])->columns(2),

                Step::make('Abstrak & HKI')->schema([
                    Textarea::make('abstract')->label('Abstrak aplikasi')->rows(6)->columnSpanFull(),
                    TextInput::make('hki')->label('HKI (isi nomor/keterangan jika sudah punya)'),
                    TextInput::make('journal_url')->label('Link jurnal (jika sudah terpublikasi)')->url()->placeholder('https://...'),
                    Textarea::make('problem')->label('Masalah (bahasa awam)')->rows(3)->columnSpanFull(),
                    Textarea::make('solution')->label('Solusi (bahasa awam)')->rows(3)->columnSpanFull(),
                ]),

                Step::make('Poster, video & berkas')->schema([
                    FileUpload::make('materials.poster')->label('Gambar poster aplikasi')->image()->disk('public')->directory('materi'),
                    TextInput::make('video_url')->label('Link YouTube video demo (jika ada)')->url()->placeholder('https://www.youtube.com/watch?v=...'),
                    FileUpload::make('materials.consent')->label('Formulir persetujuan bertanda tangan (opsional)')->disk('local')->directory('consent'),
                    FileUpload::make('materials.etika')->label('ETIKA.md bertanda tangan')->disk('local')->directory('consent')
                        ->visible(fn (?Innovation $record) => $record?->track?->code === 'cs'),
                    FileUpload::make('materials.screenshots')->label('Tangkapan layar (opsional)')->image()->multiple()->maxFiles(6)->disk('public')->directory('materi'),
                ])->columns(2),

                Step::make('Kartu hasil')->schema([
                    Group::make()->relationship('resultCard')->schema(fn (?Innovation $record) => collect($record?->track?->result_card_schema ?? [])->map(
                        fn ($f) => Textarea::make('data.'.$f['key'])->label($f['label'])->rows(2)
                    )->all())->columnSpanFull(),
                ]),

                Step::make('Persetujuan publikasi')->schema([
                    Text::make('Syarat & ketentuan publikasi karya di FASINOVA:')->weight('bold')->columnSpanFull(),
                    UnorderedList::make([
                        'Karya adalah hasil kerja tim sendiri; bagian milik pihak lain disebutkan sumbernya.',
                        'Karya, data demo, dan materi tidak memuat data pribadi atau rahasia pihak mana pun (UU PDP No. 27/2022).',
                        'Karya dan materinya (judul, abstrak, poster, video, nama tim dan pembimbing) boleh ditampilkan di etalase publik FASINOVA dan dipakai untuk kegiatan promosi Fakultas.',
                        'Demo online dijalankan di server Fakultas; kode dapat dipindai otomatis, dan karya dapat dihentikan atau ditarik dari etalase bila melanggar ketentuan atau SOP.',
                        'Karya Cyber Security tidak berisi lab rentan yang dijalankan online.',
                    ])->columnSpanFull(),
                    Checkbox::make('tos_accepted')->label('Saya (mewakili tim) telah membaca dan menyetujui persetujuan publikasi di atas.')->columnSpanFull(),
                ]),
            ])->skippable()->persistStepInQueryString()->visible(fn (string $operation) => $operation === 'edit')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->wrap(), TextColumn::make('track.name')->label('Peminatan'),
            // Filament mengisi status lewat parameter bernama $state; memakai nama lain ($s) membuat badge kosong.
            TextColumn::make('status')->badge()->formatStateUsing(fn ($state, Innovation $record) => $record->statusLabel())->color(fn ($state, Innovation $record) => $record->statusColor()),
            TextColumn::make('revision_due_at')->label('Tenggat perbaikan')->since()->visible(true),
            TextColumn::make('deployment.url')->label('Demo')->url(fn ($state) => $state, true),
        ])->recordActions([
            EditAction::make(),
            Action::make('notes')->label('Catatan PIC')->icon('heroicon-o-chat-bubble-left-text')->modalSubmitAction(false)
                ->visible(fn (Innovation $record) => $record->reviews->isNotEmpty())
                ->schema(fn (Innovation $record) => collect($record->reviews->last()->checklist_result ?? [])->filter(fn ($i) => $i['status'] === 'fix' || $i['note'])
                    ->map(fn ($i) => Text::make(($i['status'] === 'fix' ? '🔧 ' : '✅ ').$i['item'].($i['note'] ? ": {$i['note']}" : '')))->all()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInnovations::route('/'), 'create' => CreateInnovation::route('/create'), 'edit' => EditInnovation::route('/{record}/edit')];
    }
}
