<?php

namespace Tests\Feature;

use App\Filament\App\Resources\InnovationResource\Pages\CreateInnovation;
use App\Filament\App\Resources\InnovationResource\Pages\EditInnovation;
use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Regresi: buat karya lalu simpan form dengan semua kombinasi peminatan × bentuk karya (termasuk repo/health kosong). */
class EditFormMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_then_save_every_track_and_form_type(): void
    {
        $this->withoutVite()->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel('app');
        $sup = User::where('email', 'riya@esaunggul.ac.id')->first();
        $mhs = User::create(['name' => 'M', 'email' => 'm@x.test', 'password' => 'pw', 'role' => 'mahasiswa', 'nim' => '1']);
        $this->actingAs($mhs);
        $types = array_keys(config('fasinova.form_types'));

        foreach (Track::all() as $track) {
            foreach ($types as $ft) {
                $title = "T {$track->code} $ft";
                Livewire::test(CreateInnovation::class)
                    ->fillForm(['track_id' => $track->id, 'form_type' => $ft, 'title' => $title, 'supervisor_id' => $sup->id])
                    ->call('create')->assertRedirect();
                $i = Innovation::where('title', $title)->firstOrFail();
                $this->get("/app/innovations/{$i->id}/edit")->assertOk();

                $e = Livewire::test(EditInnovation::class, ['record' => $i->id]);
                foreach ($types as $to) {           // ganti bentuk karya lalu simpan, tanpa mengisi apa pun
                    $e->set('data.form_type', $to)->call('save');
                    $this->assertSame([], $e->errors()->toArray(), "{$track->code}: $ft -> $to (subdomain=".$i->deployment->fresh()->subdomain.')');
                    $this->assertSame($to, $i->fresh()->form_type, "{$track->code}: $ft -> $to");
                }
            }
        }
    }

    public function test_web_save_keeps_default_subdomain_and_rejects_taken_one(): void
    {
        $this->withoutVite()->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel('app');
        $sup = User::where('email', 'riya@esaunggul.ac.id')->first();
        $mhs = User::create(['name' => 'M', 'email' => 'm@x.test', 'password' => 'pw', 'role' => 'mahasiswa', 'nim' => '1']);
        $this->actingAs($mhs);
        $mk = fn ($t) => Innovation::create(['title' => $t, 'track_id' => Track::where('code', 'ai')->value('id'), 'owner_id' => $mhs->id, 'supervisor_id' => $sup->id, 'form_type' => 'web']);
        $a = $mk('Karya Satu'); $b = $mk('Karya Dua');

        Livewire::test(EditInnovation::class, ['record' => $a->id])->call('save')->assertHasNoFormErrors();   // subdomain bawaan = miliknya sendiri
        Livewire::test(EditInnovation::class, ['record' => $b->id])
            ->fillForm(['deployment' => ['subdomain' => $a->deployment->subdomain]])->call('save')->assertHasFormErrors(['deployment.subdomain']);
        Livewire::test(EditInnovation::class, ['record' => $b->id])
            ->fillForm(['deployment' => ['subdomain' => 'admin']])->call('save')->assertHasFormErrors(['deployment.subdomain']);
        Livewire::test(EditInnovation::class, ['record' => $b->id])
            ->fillForm(['deployment' => ['subdomain' => 'nama-baru-ok']])->call('save')->assertHasNoFormErrors();
        $this->assertSame('nama-baru-ok', $b->deployment->fresh()->subdomain);
    }
}
