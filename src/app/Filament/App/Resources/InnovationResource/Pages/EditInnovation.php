<?php

namespace App\Filament\App\Resources\InnovationResource\Pages;

use App\Filament\App\Resources\InnovationResource;
use App\Models\AuditLog;
use App\Models\EnvVariable;
use App\Services\ClaimChecker;
use App\Support\Notify;
use App\Services\RepoValidator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Http;

class EditInnovation extends EditRecord
{
    protected static string $resource = InnovationResource::class;

    /** Alur deploy hanya untuk bentuk "web" (mengikuti pilihan di form walau belum disimpan). */
    private function isWebForm(): bool
    {
        return ($this->data['form_type'] ?? $this->record->form_type) === 'web' && (bool) $this->record->track?->deployable;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // catat waktu persetujuan TOS saat pertama kali dicentang
        if (! empty($data['tos_accepted']) && ! $this->record->tos_accepted) $data['tos_accepted_at'] = now();
        if (empty($data['tos_accepted'])) $data['tos_accepted_at'] = null;
        return $data;
    }

    protected function afterSave(): void
    {
        AuditLog::record('env.update', $this->record->id);
        $d = $this->record->deployment;
        if ($d && $d->status === 'live') $d->update(['needs_redeploy' => true]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validate')->label('Validasi repo')->icon('heroicon-o-check-badge')->visible(fn () => $this->isWebForm())->action(function () {
                $this->save(); // simpan URL/tag dulu
                $items = (new RepoValidator)->validate($this->record->fresh('repository', 'track'));
                $bad = collect($items)->where('ok', false)->count();
                Notification::make()->title($bad ? "$bad pemeriksaan gagal" : 'Repo lolos semua pemeriksaan')->{$bad ? 'danger' : 'success'}()->send();
                $this->fillForm();
            }),
            Action::make('envExample')->label('Ambil kunci dari .env.example')->icon('heroicon-o-arrow-down-tray')->visible(fn () => $this->isWebForm())->action(function () {
                $r = $this->record->repository;
                $raw = Http::get("https://raw.githubusercontent.com/{$r->owner}/{$r->name}/".($r->tag ?: 'HEAD').'/'.($r->validation_report['env_example'] ?? '.env.example'));
                if (! $raw->ok()) { Notification::make()->title('.env.example tidak ditemukan (validasi repo dulu).')->danger()->send(); return; }
                preg_match_all('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/m', $raw->body(), $m);
                foreach (array_unique($m[1]) as $key) {
                    EnvVariable::firstOrCreate(['innovation_id' => $this->record->id, 'key' => $key], ['is_secret' => true, 'updated_by' => auth()->id()]);
                }
                $this->fillForm();
                Notification::make()->title(count($m[1]).' kunci ditambahkan')->success()->send();
            }),
            Action::make('askSupervisor')->label('Minta persetujuan pembimbing')->icon('heroicon-o-bell-alert')
                ->visible(fn () => ! $this->record->supervisor_approved_at && $this->record->supervisor_id)->requiresConfirmation()->action(function () {
                    Notify::innovation([$this->record->supervisor], $this->record, 'Permintaan persetujuan karya',
                        auth()->user()->name.' meminta persetujuan Anda: '.$this->record->title, 'warning', '/dosen/bimbingan');
                    Notification::make()->title('Notifikasi terkirim ke pembimbing')->success()->send();
                }),
            Action::make('submit')->label('Kirim paket')->icon('heroicon-o-paper-airplane')->color('success')
                ->action(function () {
                    $this->save();
                    $i = $this->record->fresh('repository', 'track', 'resultCard');
                    $m = $i->materials ?? [];
                    $card = $i->resultCard?->data ?? [];
                    $problems = collect([
                        ! $i->supervisor_approved_at ? 'Pembimbing belum menyetujui.' : null,
                        blank($i->form_type) ? 'Bentuk karya belum dipilih.' : null,
                        blank($i->study_program_id) ? 'Program studi belum dipilih (langkah Pembimbing & tim).' : null,
                        $i->isDeployable() && empty($i->repository?->validation_report['ok']) ? 'Validasi repo belum lolos.' : null,
                        blank($i->abstract) ? 'Abstrak belum diisi.' : null,
                        empty($m['poster']) ? 'Gambar poster belum diunggah.' : null,
                        ! $i->tos_accepted ? 'Persetujuan publikasi (TOS) belum dicentang.' : null,
                        $i->track->code === 'cs' && empty($m['etika']) ? 'ETIKA.md bertanda tangan belum diunggah.' : null,
                        $i->isDeployable() && blank($i->deployment?->stack) ? 'Stack aplikasi belum dipilih.' : null,
                        $i->isDeployable() && blank($i->deployment?->subdomain) ? 'Subdomain belum diisi.' : null,
                        collect($i->track->result_card_schema ?? [])->contains(fn ($f) => ($f['required'] ?? false) && blank($card[$f['key']] ?? null)) ? 'Kartu hasil belum lengkap.' : null,
                        ClaimChecker::blocking($card, $i->track->code),
                    ])->filter();
                    if ($problems->isNotEmpty()) {
                        Notification::make()->title('Belum bisa dikirim')->body($problems->implode("\n"))->danger()->send();
                        return;
                    }
                    $i->resultCard->update(['claim_warnings' => ClaimChecker::warnings($card, $i->track->code)]);
                    $i->update(['status' => 'submitted', 'consent_ok' => $i->tos_accepted, 'revision_due_at' => null]);
                    AuditLog::record('submit', $i->id);
                    Notify::innovation(Notify::pics($i), $i, 'Karya menunggu diperiksa', $i->title.' ('.$i->track->name.')', 'warning');
                    $this->redirect(InnovationResource::getUrl('index'));
                }),
        ];
    }
}
