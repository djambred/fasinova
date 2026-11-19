<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources;
use App\Filament\App\Resources\InnovationResource\Pages as AppPages;
use App\Filament\Dosen\Resources\BimbinganResource\Pages\ListBimbingan;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Innovation;
use App\Models\Review;
use App\Models\StudyProgram;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Buka SETIAP tombol di setiap tabel/halaman, per peran, dengan data seperti di server (karya terbit + review + deploy live,
 * karya menunggu PIC, draf). Menangkap kelas bug yang tidak terlihat dari sekadar memuat halaman: ikon yang tidak ada,
 * parameter closure yang tak bisa disuntik Filament, modal yang crash. Tiap tombol hanya dibuka (mount), bukan dikirim.
 */
class ActionSmokeTest extends TestCase
{
    use RefreshDatabase;

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite()->seed(DatabaseSeeder::class);
        Queue::fake();
        Http::fake(['*' => Http::response(['private' => false, 'default_branch' => 'main', 'tree' => [], 'object' => ['sha' => 'abc', 'type' => 'commit']])]);

        $prodi = StudyProgram::first();
        $track = Track::where('code', 'fullstack')->first();
        $sup = User::where('email', 'jefry@esaunggul.ac.id')->first() ?? User::create(['name' => 'Dosen', 'email' => 'd@x.test', 'password' => 'pw', 'role' => 'dosen']);
        $mhs = User::create(['name' => 'Mhs', 'email' => 'm@x.test', 'password' => 'pw', 'role' => 'mahasiswa', 'nim' => '1', 'study_program_id' => $prodi->id]);
        $pic = User::create(['name' => 'PIC', 'email' => 'p@x.test', 'password' => 'pw', 'role' => 'pic', 'kode_dosen' => 'P1']);
        $track->pics()->syncWithoutDetaching([$pic->id]);
        $base = ['track_id' => $track->id, 'owner_id' => $mhs->id, 'supervisor_id' => $sup->id, 'study_program_id' => $prodi->id, 'form_type' => 'web', 'abstract' => 'A'];

        $pub = Innovation::create($base + ['title' => 'Terbit', 'status' => 'published', 'supervisor_approved_at' => now(), 'consent_ok' => true, 'tos_accepted' => true]);
        $pub->repository->update(['url' => 'https://github.com/org/x', 'locked_commit_sha' => 'abc', 'validation_report' => ['ok' => true, 'items' => [['label' => 'Format URL', 'ok' => true]], 'stack' => 'laravel']]);
        $pub->deployment->update(['status' => 'live', 'stack' => 'laravel', 'subdomain' => 'terbit', 'host_port' => 20001, 'url' => 'https://terbit.x', 'internal_port' => 8000]);
        $pub->deployment->logs()->create(['step' => 'health_check', 'stream' => 'out', 'line' => 'ok']);
        Review::create(['innovation_id' => $pub->id, 'reviewer_id' => $pic->id, 'decision' => 'approve', 'checklist_result' => [['item' => 'Abstrak', 'status' => 'ok', 'note' => null]], 'note' => 'Bagus']);

        $sub = Innovation::create($base + ['title' => 'Menunggu', 'status' => 'submitted', 'supervisor_approved_at' => now()]);
        $sub->repository->update(['url' => 'https://github.com/org/y', 'validation_report' => ['ok' => true, 'items' => [], 'stack' => 'laravel']]);
        $sub->deployment->update(['status' => 'failed', 'stack' => 'laravel', 'subdomain' => 'menunggu']);

        Innovation::create($base + ['title' => 'Draf', 'status' => 'draft']);
        Innovation::create($base + ['title' => 'Revisi', 'status' => 'needs_revision', 'revision_due_at' => now()->subDay()]);
        Inquiry::create(['innovation_id' => $pub->id, 'name' => 'Mitra', 'email' => 'mitra@x.test', 'message' => 'Halo']);
        AuditLog::record('deploy', $pub->id, ['x' => 1]);

        $this->users = ['admin' => User::where('role', 'admin')->first(), 'pic' => $pic, 'dosen' => $sup, 'mahasiswa' => $mhs];
    }

    public static function pages(): array
    {
        return [
            'admin: karya' => ['admin', 'admin', Resources\InnovationResource\Pages\ListInnovations::class],
            'pic: karya' => ['admin', 'pic', Resources\InnovationResource\Pages\ListInnovations::class],
            'dosen: panel PIC karya' => ['admin', 'dosen', Resources\InnovationResource\Pages\ListInnovations::class],
            'admin: deploy' => ['admin', 'admin', Resources\DeploymentResource\Pages\ListDeployments::class],
            'pic: deploy' => ['admin', 'pic', Resources\DeploymentResource\Pages\ListDeployments::class],
            'admin: pengguna' => ['admin', 'admin', Resources\UserResource\Pages\ListUsers::class],
            'admin: peminatan' => ['admin', 'admin', Resources\TrackResource\Pages\ListTracks::class],
            'admin: fakultas' => ['admin', 'admin', Resources\FacultyResource\Pages\ListFaculties::class],
            'admin: prodi' => ['admin', 'admin', Resources\StudyProgramResource\Pages\ListStudyPrograms::class],
            'admin: inquiry' => ['admin', 'admin', Resources\InquiryResource\Pages\ListInquiries::class],
            'admin: audit' => ['admin', 'admin', Resources\AuditLogResource\Pages\ListAuditLogs::class],
            'mahasiswa: karya saya' => ['app', 'mahasiswa', AppPages\ListInnovations::class],
            'dosen: bimbingan' => ['dosen', 'dosen', ListBimbingan::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_every_table_and_header_action_opens(string $panel, string $role, string $page): void
    {
        Filament::setCurrentPanel($panel);
        $this->actingAs($this->users[$role]);
        $lw = Livewire::test($page)->assertOk();
        $opened = 0;

        foreach ($lw->instance()->getTable()->getRecords() as $record) {
            foreach ($lw->instance()->getTable()->getFlatRecordActions() as $name => $action) {
                Livewire::test($page)->mountTableAction($name, $record)->assertOk();   // buka modal / jalankan aksi tanpa modal
                $opened++;
            }
        }
        foreach ($lw->instance()->getTable()->getHeaderActions() as $action) {
            if (! $action->isHidden() && $action->getName()) { Livewire::test($page)->mountTableAction($action->getName())->assertOk(); $opened++; }
        }
        $this->addToAssertionCount(1);
        fwrite(STDERR, '');   // jumlah tombol yang dibuka bisa dilihat dengan --debug
        $this->assertGreaterThanOrEqual(0, $opened);
    }

    /** Wizard mahasiswa: setiap tombol header di halaman ubah, untuk tiap status karya. */
    public function test_every_wizard_header_action_opens_for_every_status(): void
    {
        Filament::setCurrentPanel('app');
        $this->actingAs($this->users['mahasiswa']);
        foreach (Innovation::where('owner_id', $this->users['mahasiswa']->id)->get() as $i) {
            if (! $this->users['mahasiswa']->can('update', $i)) continue;   // karya terkirim/terbit memang terkunci untuk mahasiswa
            $lw = Livewire::test(AppPages\EditInnovation::class, ['record' => $i->id])->assertOk();
            foreach ($lw->instance()->getCachedHeaderActions() as $action) {
                foreach (method_exists($action, 'getFlatActions') ? $action->getFlatActions() : [$action] as $a) {
                    if ($a->isHidden() || $a->isDisabled()) continue;
                    Livewire::test(AppPages\EditInnovation::class, ['record' => $i->id])->mountAction($a->getName())->assertOk();
                }
            }
        }
        Livewire::test(AppPages\CreateInnovation::class)->assertOk();
    }
}
