<?php

namespace Tests\Feature;

use App\Filament\App\Resources\InnovationResource\Pages\CreateInnovation;
use App\Filament\App\Resources\InnovationResource\Pages\EditInnovation;
use App\Models\Innovation;
use App\Models\StudyProgram;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Alur mahasiswa dari membuat karya sampai Kirim paket, termasuk karya lama tanpa prodi (tidak boleh membuat Simpan buntu). */
class StudentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setUpStudent(): array
    {
        $this->withoutVite()->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel('app');
        $prodi = StudyProgram::first();
        $sup = User::where('email', 'riya@esaunggul.ac.id')->first();
        $mhs = User::create(['name' => 'Mhs', 'email' => 'mhs@x.test', 'password' => 'pw', 'role' => 'mahasiswa', 'nim' => '77', 'study_program_id' => $prodi->id]);
        $this->actingAs($mhs);
        return [$mhs, $sup, $prodi];
    }

    private function completeDraft(User $mhs, User $sup, ?StudyProgram $prodi): Innovation
    {
        Storage::fake('public');                                    // unggahan Filament membuang path yang berkasnya tidak ada
        Storage::disk('public')->put('materi/poster.png', 'png');
        $track = Track::where('code', 'fullstack')->first();
        $i = Innovation::create(['title' => 'Alur Uji', 'track_id' => $track->id, 'owner_id' => $mhs->id, 'supervisor_id' => $sup->id,
            'study_program_id' => $prodi?->id, 'form_type' => 'web', 'abstract' => 'Abstrak uji', 'tos_accepted' => true,
            'supervisor_approved_at' => now(), 'materials' => ['poster' => 'materi/poster.png']]);
        $i->repository->update(['url' => 'https://github.com/org/fs-demo', 'validation_report' => ['ok' => true, 'items' => [], 'tag_sha' => 'abc']]);
        $i->deployment->update(['stack' => 'laravel', 'subdomain' => 'alur-uji']);
        $i->resultCard->update(['data' => collect($track->result_card_schema)->mapWithKeys(fn ($f) => [$f['key'] => 'isi'])->all()]);
        return $i->fresh();
    }

    public function test_create_from_new_karya_form_then_land_on_edit(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        Livewire::test(CreateInnovation::class)
            ->fillForm(['track_id' => Track::where('code', 'fullstack')->value('id'), 'form_type' => 'web', 'title' => 'Karya Baru', 'study_program_id' => $prodi->id, 'supervisor_id' => $sup->id])
            ->call('create')->assertHasNoFormErrors();
        $i = Innovation::where('title', 'Karya Baru')->firstOrFail();
        Livewire::test(CreateInnovation::class)->assertOk();
        $this->assertSame(url("/app/innovations/{$i->id}/edit"), (new CreateInnovation)->getResource()::getUrl('edit', ['record' => $i]));
        $this->assertSame($mhs->id, $i->owner_id);
        $this->assertSame($prodi->id, $i->study_program_id);
        $this->get("/app/innovations/{$i->id}/edit")->assertOk();
    }

    public function test_student_list_shows_readable_status_badges(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $draft = $this->completeDraft($mhs, $sup, $prodi);
        $sent = Innovation::create(['title' => 'Sudah Dikirim', 'track_id' => $draft->track_id, 'owner_id' => $mhs->id, 'status' => 'submitted']);
        $late = Innovation::create(['title' => 'Telat', 'track_id' => $draft->track_id, 'owner_id' => $mhs->id, 'status' => 'needs_revision', 'revision_due_at' => now()->subDay()]);
        $list = \Livewire\Livewire::test(\App\Filament\App\Resources\InnovationResource\Pages\ListInnovations::class);   // tabel dirender oleh komponen Livewire
        foreach (['Draf', 'Menunggu PIC', 'Terlambat'] as $label) $list->assertSee($label);                                          // dulu badge kosong
        $this->assertSame('warning', $sent->statusColor());
        $this->assertSame('danger', $late->statusColor());
        \Livewire\Livewire::test(\App\Filament\App\Widgets\MyInnovations::class)->assertSee('Menunggu PIC')->assertSee('Draf');   // widget dasbor juga
    }

    public function test_old_karya_without_program_can_still_be_saved_and_is_prefilled(): void
    {
        [$mhs, $sup] = $this->setUpStudent();
        $i = $this->completeDraft($mhs, $sup, null);                       // dibuat sebelum aturan prodi ada
        $this->assertNull($i->study_program_id);
        Livewire::test(EditInnovation::class, ['record' => $i->id])
            ->assertFormSet(['study_program_id' => $mhs->study_program_id])   // terisi dari prodi mahasiswa
            ->call('save')->assertHasNoFormErrors();
    }

    public function test_submit_succeeds_when_everything_is_complete_and_notifies_pic(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $i = $this->completeDraft($mhs, $sup, $prodi);
        Livewire::test(EditInnovation::class, ['record' => $i->id])->callAction('submit');
        $i->refresh();
        $this->assertSame('submitted', $i->status);
        $this->assertTrue((bool) $i->consent_ok);
        // PIC = pembimbing + kaprodi/dekan prodi karya (+ PIC peminatan); admin hanya cadangan bila tidak ada satu pun
        $this->assertTrue($sup->notifications()->get()->contains(fn ($n) => str_contains(json_encode($n->data), 'menunggu diperiksa')));
    }

    public function test_submit_is_blocked_with_clear_reasons_for_each_missing_piece(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $cases = [
            'Program studi belum dipilih' => function (Innovation $i) use ($mhs) {   // tanpa prodi di karya DAN di profil mahasiswa (tak ada yang bisa mengisi otomatis)
                $i->update(['study_program_id' => null]);
                $mhs->update(['study_program_id' => null]);
                $this->actingAs($mhs->fresh());
            },
            'Pembimbing belum menyetujui' => fn (Innovation $i) => $i->update(['supervisor_approved_at' => null]),
            'Abstrak belum diisi' => fn (Innovation $i) => $i->update(['abstract' => null]),
            'Gambar poster belum diunggah' => fn (Innovation $i) => $i->update(['materials' => []]),
            'TOS' => fn (Innovation $i) => $i->update(['tos_accepted' => false]),
            'Stack aplikasi belum dipilih' => fn (Innovation $i) => $i->deployment->update(['stack' => null]),
            'Validasi repo belum lolos' => fn (Innovation $i) => $i->repository->update(['validation_report' => ['ok' => false]]),
        ];
        foreach ($cases as $needle => $break) {
            $i = $this->completeDraft($mhs, $sup, $prodi);
            $break($i);
            $t = Livewire::test(EditInnovation::class, ['record' => $i->id]);
            // Simpan di wizard tidak boleh mengunci karena kekurangan ini; hanya Kirim paket yang menolak
            $t->call('save');
            $t->callAction('submit');
            $this->assertSame('draft', $i->fresh()->status, "harus tetap draf bila: $needle");
            $i->delete();
        }
    }

    private function fakeGithub(): void
    {
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        $tree = ['tree' => collect(['docker-compose.yml', 'README.md', 'src/.env.example'])->map(fn ($p) => ['path' => $p])->all()];
        \Illuminate\Support\Facades\Http::fake([
            'api.github.com/repos/org/fs-demo/git/ref/tags/*' => \Illuminate\Support\Facades\Http::response([], 404),
            'api.github.com/repos/org/fs-demo/git/ref/heads/*' => \Illuminate\Support\Facades\Http::response(['object' => ['sha' => 'beef01', 'type' => 'commit']]),
            'api.github.com/repos/org/fs-demo/git/trees/beef01*' => \Illuminate\Support\Facades\Http::response($tree),
            'api.github.com/repos/org/fs-demo' => \Illuminate\Support\Facades\Http::response(['private' => false, 'default_branch' => 'main']),
            'raw.githubusercontent.com/org/fs-demo/*/.env.example' => \Illuminate\Support\Facades\Http::response("APP_KEY=\nDB_HOST=db\n"),
            'raw.githubusercontent.com/*' => \Illuminate\Support\Facades\Http::response("services:\n  web:\n    image: x\n"),
        ]);
        config(['fasinova.github_org' => 'org']);
    }

    public function test_validate_repo_button_keeps_fields_filled_and_shows_results(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $i = $this->completeDraft($mhs, $sup, $prodi);
        $i->repository->update(['url' => 'https://github.com/org/fs-demo.git', 'tag' => 'main', 'validation_report' => null]);
        $this->fakeGithub();

        $t = Livewire::test(EditInnovation::class, ['record' => $i->id])->callAction('validate');
        $t->assertFormSet(['repository.url' => 'https://github.com/org/fs-demo', 'repository.tag' => 'main']);   // dulu: kosong setelah validasi
        $report = $i->repository->fresh()->validation_report;
        $this->assertTrue($report['ok']);
        $t->call('save')->assertHasNoFormErrors();                                                                // Simpan sesudahnya tidak menimpa dengan kosong
        $this->assertSame('https://github.com/org/fs-demo', $i->repository->fresh()->url);
        $this->assertSame('main', $i->repository->fresh()->tag);
    }

    public function test_env_example_button_keeps_form_and_adds_keys(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $i = $this->completeDraft($mhs, $sup, $prodi);
        $i->repository->update(['url' => 'https://github.com/org/fs-demo', 'tag' => 'main', 'owner' => 'org', 'name' => 'fs-demo', 'validation_report' => ['ok' => true, 'env_example' => 'src/.env.example']]);
        $this->fakeGithub();

        $t = Livewire::test(EditInnovation::class, ['record' => $i->id])->callAction('envExample');
        $this->assertEqualsCanonicalizing(['APP_KEY', 'DB_HOST'], $i->envVariables()->pluck('key')->all());
        $t->assertFormSet(['repository.url' => 'https://github.com/org/fs-demo', 'repository.tag' => 'main']);
    }

    public function test_submit_action_runs_without_server_error(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $i = $this->completeDraft($mhs, $sup, $prodi);
        Livewire::test(EditInnovation::class, ['record' => $i->id])->callAction('submit')->assertOk();   // dulu 500: closure Repeater memakai $d
        $this->assertSame('submitted', $i->fresh()->status);
    }

    public function test_fullstack_card_is_optional_but_ai_card_stays_required(): void
    {
        [$mhs, $sup, $prodi] = $this->setUpStudent();
        $i = $this->completeDraft($mhs, $sup, $prodi);                       // Full Stack, kartu hasil kosong
        $this->assertSame([false], collect($i->track->result_card_schema)->pluck('required')->unique()->all());
        Livewire::test(EditInnovation::class, ['record' => $i->id])->callAction('submit');
        $this->assertSame('submitted', $i->fresh()->status);                 // terkirim tanpa kartu hasil
        $this->assertSame([true], collect(Track::where('code', 'ai')->first()->result_card_schema)->pluck('required')->unique()->all());
    }
}
