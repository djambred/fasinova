<?php

namespace Tests\Feature;

use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function make(string $status): Innovation
    {
        $this->seed(DatabaseSeeder::class);
        $u = User::create(['name' => 'Mhs', 'email' => 'm@x.test', 'password' => 'pw', 'role' => 'mahasiswa']);
        return Innovation::create(['title' => 'Deteksi Hoaks', 'track_id' => Track::where('code', 'ai')->value('id'), 'owner_id' => $u->id, 'status' => $status, 'problem' => 'p']);
    }

    public function test_only_published_visible_and_child_rows_created(): void
    {
        $i = $this->make('published');
        $this->assertNotNull($i->repository);
        $this->assertSame('deteksi-hoaks', $i->deployment->subdomain);
        $this->get('/')->assertOk()->assertSee('Deteksi Hoaks');
        $this->get('/karya/deteksi-hoaks')->assertOk();
        $i->update(['status' => 'draft']);
        $this->get('/karya/deteksi-hoaks')->assertNotFound();
    }

    public function test_inquiry_rate_limited_after_3(): void
    {
        $this->make('published');
        $p = ['name' => 'A', 'email' => 'a@b.co', 'message' => 'hai', 'pdp' => 1];
        for ($n = 0; $n < 3; $n++) $this->post('/karya/deteksi-hoaks/kolaborasi', $p)->assertRedirect();
        $this->post('/karya/deteksi-hoaks/kolaborasi', $p)->assertStatus(429);
        $this->assertDatabaseCount('inquiries', 3);
    }

    public function test_env_value_encrypted_and_hidden(): void
    {
        $i = $this->make('draft');
        $i->envVariables()->create(['key' => 'API_KEY', 'value' => 'sekret-123']);
        $this->assertStringNotContainsString('sekret-123', \DB::table('env_variables')->value('value'));
        $this->assertArrayNotHasKey('value', $i->envVariables()->first()->toArray());
    }

    public function test_panels_render_and_authorize(): void
    {
        $i = $this->make('draft');
        $mhs = $i->owner;
        $this->actingAs($mhs)->get('/admin/innovations')->assertForbidden();
        $this->actingAs($mhs)->get('/app/innovations')->assertOk();
        $this->actingAs($mhs)->get('/app/innovations/create')->assertOk();
        $this->actingAs($mhs)->get("/app/innovations/{$i->id}/edit")->assertOk();

        $admin = User::where('role', 'admin')->first();
        foreach (['', 'innovations', 'deployments', 'tracks', 'inquiries', 'users', 'faculties', 'study-programs', 'audit-logs', 'settings'] as $p) {
            $this->actingAs($admin)->get(rtrim("/admin/$p", "/"));
            $r = $this->actingAs($admin)->get(rtrim("/admin/$p", "/"));
            $this->assertSame(200, $r->status(), "/admin/$p ".(collect($r->exception?->getTrace() ?? [])->filter(fn ($t) => str_contains($t['file'] ?? '', '/app/') || str_contains($t['file'] ?? '', 'filament/tables/src'))->take(4)->map(fn ($t) => ($t['file'] ?? '').':'.($t['line'] ?? ''))->implode(' | ')));
        }
    }

    public function test_other_student_cannot_open_innovation(): void
    {
        $i = $this->make('draft');
        $other = User::create(['name' => 'X', 'email' => 'x@x.test', 'password' => 'pw', 'role' => 'mahasiswa']);
        $this->actingAs($other)->get("/app/innovations/{$i->id}/edit")->assertNotFound();
    }

    public function test_jabatan_and_program_sync(): void
    {
        $f = \App\Models\Faculty::create(['code' => 'FIK', 'name' => 'Ilmu Komputer']);
        $p = \App\Models\StudyProgram::create(['faculty_id' => $f->id, 'code' => 'TI', 'name' => 'Teknik Informatika']);
        $d = User::create(['name' => 'Dr D', 'email' => 'd@x.test', 'password' => 'pw', 'role' => 'dosen', 'study_program_id' => $p->id]);
        $this->assertSame('Teknik Informatika', $d->study_program);
        $this->assertNull($d->jabatan());
        $f->update(['dean_id' => $d->id]);
        $p->update(['head_id' => $d->id]);
        $this->assertSame('Dekan, Kaprodi', $d->jabatan());
    }

    private function dosenFixture(): array
    {
        $i = $this->make('draft');
        $dosen = User::create(['name' => 'Dr', 'email' => 'dr@x.test', 'password' => 'pw', 'role' => 'dosen', 'kode_dosen' => 'D01']);
        $i->update(['supervisor_id' => $dosen->id]);
        return [$i, $dosen];
    }

    public function test_student_dashboard(): void
    {
        [$i] = $this->dosenFixture();
        $this->actingAs($i->owner)->get('/app')->assertOk()->assertSee('Karya saya');
    }

    public function test_student_cannot_open_lecturer_panel(): void
    {
        [$i] = $this->dosenFixture();
        $this->actingAs($i->owner)->get('/dosen')->assertForbidden();
    }

    public function test_lecturer_dashboard_and_list(): void
    {
        [, $dosen] = $this->dosenFixture();
        $this->actingAs($dosen)->get('/dosen')->assertOk();
        $this->actingAs($dosen)->get('/dosen/bimbingan')->assertOk()->assertSee('Deteksi Hoaks');
    }

    public function test_lecturer_cannot_open_student_panel(): void
    {
        [, $dosen] = $this->dosenFixture();
        $this->actingAs($dosen)->get('/app')->assertForbidden();
    }

    public function test_supervisor_can_approve_and_kaprodi_sees_program(): void
    {
        $i = $this->make('draft');
        $f = \App\Models\Faculty::create(['code' => 'F', 'name' => 'F']);
        $p = \App\Models\StudyProgram::create(['faculty_id' => $f->id, 'code' => 'P', 'name' => 'P']);
        $i->update(['study_program_id' => $p->id]);                     // cakupan kaprodi mengikuti prodi pada KARYA
        $sup = User::create(['name' => 'S', 'email' => 's@x.test', 'password' => 'pw', 'role' => 'dosen', 'kode_dosen' => 'S1']);
        $kap = User::create(['name' => 'K', 'email' => 'k@x.test', 'password' => 'pw', 'role' => 'dosen', 'kode_dosen' => 'K1']);
        $p->update(['head_id' => $kap->id]);
        $i->update(['supervisor_id' => $sup->id]);

        \Filament\Facades\Filament::setCurrentPanel('dosen');
        $this->actingAs($kap);
        $this->assertCount(1, \App\Filament\Dosen\Resources\BimbinganResource::getEloquentQuery()->get());
        $this->actingAs($sup);
        \Livewire\Livewire::test(\App\Filament\Dosen\Resources\BimbinganResource\Pages\ListBimbingan::class)->callTableAction('approve', $i);
        $this->assertNotNull($i->fresh()->supervisor_approved_at);
        $stranger = User::create(['name' => 'X', 'email' => 'x2@x.test', 'password' => 'pw', 'role' => 'dosen', 'kode_dosen' => 'X1']);
        $this->actingAs($stranger);
        $this->assertCount(0, \App\Filament\Dosen\Resources\BimbinganResource::getEloquentQuery()->get());
    }

    public function test_detail_shows_abstract_hki_and_youtube_embed(): void
    {
        $i = $this->make('published');
        $i->update(['abstract' => 'Abstrak uji', 'hki' => 'EC00202612345', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->get('/karya/deteksi-hoaks')->assertOk()->assertSee('Abstrak uji')->assertSee('EC00202612345')
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
        $i->update(['video_url' => 'https://evil.example/x']);
        $this->get('/karya/deteksi-hoaks')->assertDontSee('<iframe', false);
    }

    public function test_detail_shows_journal_link_and_form_type(): void
    {
        $i = $this->make('published');
        $i->update(['form_type' => 'mobile', 'journal_url' => 'https://jurnal.example/artikel/1']);
        $this->get('/karya/deteksi-hoaks')->assertOk()->assertSee('Aplikasi mobile')->assertSee('https://jurnal.example/artikel/1');
    }

    public function test_student_saves_tos_journal_and_form_type_with_timestamp(): void
    {
        $i = $this->make('draft');
        $i->update(['supervisor_id' => User::create(['name' => 'Dr', 'email' => 'dr9@x.test', 'password' => 'pw', 'role' => 'dosen', 'kode_dosen' => 'D9'])->id]);
        \Filament\Facades\Filament::setCurrentPanel('app');
        $this->actingAs($i->owner);
        \Livewire\Livewire::test(\App\Filament\App\Resources\InnovationResource\Pages\EditInnovation::class, ['record' => $i->id])
            ->fillForm(['form_type' => 'mobile', 'journal_url' => 'https://jurnal.example/a', 'tos_accepted' => true])
            ->call('save')->assertHasNoFormErrors();
        $i->refresh();
        $this->assertSame('mobile', $i->form_type);
        $this->assertTrue($i->tos_accepted);
        $this->assertNotNull($i->tos_accepted_at);
        $this->assertFalse($i->isDeployable());
        $i->update(['form_type' => 'web']);
        $this->assertTrue($i->isDeployable());   // track AI boleh deploy
    }

    public function test_behind_https_proxy_urls_are_https(): void
    {
        $this->make('published');
        $html = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'fasinova.test'])->get('/masuk')->assertOk()->getContent();
        $this->assertStringNotContainsString('http://localhost', $html);
        $this->assertSame('https', app('request')->getScheme());
    }
}
