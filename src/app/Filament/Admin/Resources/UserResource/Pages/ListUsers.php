<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\Track;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Semua')->badge(User::count())];
        foreach (['admin' => 'Admin', 'pic' => 'PIC', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'] as $role => $label) {
            $tabs[$role] = Tab::make($label)->badge(User::where('role', $role)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', $role));
        }
        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')->label('Impor peserta TA (CSV)')->icon('heroicon-o-arrow-up-tray')
                ->schema([FileUpload::make('file')->label('CSV: nim,nama,email,prodi,peminatan,pembimbing (email)')
                    ->disk('local')->directory('imports')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->required()])
                ->action(function (array $data) {
                    $fh = fopen(Storage::disk('local')->path($data['file']), 'r');
                    $head = array_map(fn ($h) => Str::lower(trim($h)), fgetcsv($fh) ?: []);
                    $n = 0;
                    while (($row = fgetcsv($fh)) !== false) {
                        $r = array_combine($head, array_pad($row, count($head), null));
                        if (empty($r['nim']) || empty($r['email'])) continue;
                        User::updateOrCreate(['nim' => $r['nim']], [
                            'name' => $r['nama'], 'email' => $r['email'], 'study_program' => $r['prodi'] ?? null, 'role' => 'mahasiswa',
                        ] + (User::where('nim', $r['nim'])->exists() ? [] : ['password' => 'password']));
                        if (! empty($r['pembimbing'])) {
                            User::firstOrCreate(['email' => $r['pembimbing']], ['name' => Str::before($r['pembimbing'], '@'), 'role' => 'dosen', 'password' => 'password']);
                        }
                        $n++;
                    }
                    fclose($fh);
                    Notification::make()->title("$n peserta diimpor. Kata sandi awal: password (minta diganti lewat menu profil).")->success()->send();
                }),
        ];
    }
}
