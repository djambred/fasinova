<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_each_role_lands_on_its_own_panel(): void
    {
        foreach (['admin' => '/admin', 'pic' => '/admin', 'dosen' => '/dosen', 'mahasiswa' => '/app'] as $role => $home) {
            User::create(['name' => $role, 'email' => "$role@x.test", 'password' => 'secret-1', 'role' => $role]);
            $this->post('/masuk', ['email' => "$role@x.test", 'password' => 'secret-1'])->assertRedirect($home);
            $this->post('/keluar')->assertRedirect('/masuk');
        }
    }

    public function test_wrong_password_and_guest_redirects(): void
    {
        User::create(['name' => 'a', 'email' => 'a@x.test', 'password' => 'secret-1', 'role' => 'admin']);
        $this->post('/masuk', ['email' => 'a@x.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->get('/masuk')->assertOk()->assertSee('Masuk ke akun Anda');
        foreach (['/admin', '/app', '/dosen'] as $panel) {
            $this->get($panel)->assertRedirect(route('filament.'.ltrim($panel, '/').'.auth.login'));
            $this->get($panel.'/login')->assertRedirect(route('login'));
        }
    }
}
