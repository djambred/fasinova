<?php

namespace Tests\Feature;

use App\Models\StudyProgram;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $over = []): array
    {
        return array_merge(['name' => 'Siti Mahasiswa', 'nim' => '20210801001', 'email' => 'Siti@Student.EsaUnggul.ac.id',
            'study_program_id' => StudyProgram::first()->id, 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'pernyataan' => 1], $over);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite()->seed(DatabaseSeeder::class);
    }

    public function test_page_uses_site_theme_and_links_from_login(): void
    {
        $this->get('/daftar')->assertOk()->assertSee('fx-auth wide', false)->assertSee('img/logo-ueu.png')->assertSee('Teknik Informatika');
        $this->get('/masuk')->assertOk()->assertSee('/daftar');
    }

    public function test_student_registers_and_lands_in_student_panel(): void
    {
        $this->post('/daftar', $this->form())->assertRedirect('/app');
        $u = User::where('nim', '20210801001')->first();
        $this->assertSame('mahasiswa', $u->role);
        $this->assertSame('siti@student.esaunggul.ac.id', $u->email);   // dinormalisasi huruf kecil
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('rahasia123', $u->password));
        $this->assertSame(1, User::where('role', 'admin')->first()->notifications()->count());   // admin diberi tahu
        $this->get('/daftar')->assertRedirect('/app');                                           // sudah login
    }

    public function test_rejects_non_campus_email_duplicates_weak_password_and_missing_consent(): void
    {
        User::create(['name' => 'Lama', 'email' => 'lama@student.esaunggul.ac.id', 'password' => 'pw', 'role' => 'mahasiswa', 'nim' => '111111']);
        $cases = [
            ['email' => 'siti@gmail.com'],                                       // bukan email kampus
            ['email' => 'siti@student.esaunggul.ac.id.evil.com'],               // akhiran palsu
            ['nim' => '111111'],                                                 // NIM sudah dipakai
            ['email' => 'lama@student.esaunggul.ac.id'],                         // email sudah dipakai
            ['password' => 'pendek1', 'password_confirmation' => 'pendek1'],     // < 8
            ['password' => 'hanyahuruf', 'password_confirmation' => 'hanyahuruf'],
            ['password_confirmation' => 'beda12345'],
            ['pernyataan' => null],
            ['study_program_id' => 99999],
        ];
        foreach ($cases as $over) {
            $this->post('/daftar', $this->form($over))->assertSessionHasErrors();
        }
        $this->assertSame(0, User::where('name', 'Siti Mahasiswa')->count());
        $this->assertGuest();
    }

    public function test_role_cannot_be_injected(): void
    {
        $this->post('/daftar', $this->form(['role' => 'admin']))->assertRedirect('/app');
        $this->assertSame('mahasiswa', User::where('nim', '20210801001')->value('role'));
    }
}
