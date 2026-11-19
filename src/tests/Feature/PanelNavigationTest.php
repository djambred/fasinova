<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Siapa boleh membuka panel mana, dan tautan antar-panel (dosen/PIC tidak boleh terjebak di satu panel). Satu peran per tes (sesi bersih). */
class PanelNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $this->withoutVite()->seed(DatabaseSeeder::class);
        return User::create(['name' => $role, 'email' => "$role@nav.test", 'password' => 'pw', 'role' => $role, 'kode_dosen' => $role === 'mahasiswa' ? null : 'K1', 'nim' => $role === 'mahasiswa' ? '5' : null]);
    }

    public static function roles(): array
    {
        return [   // peran => [/admin, /dosen, /app]
            'mahasiswa' => ['mahasiswa', 403, 403, 200],
            'dosen' => ['dosen', 200, 200, 403],
            'pic' => ['pic', 200, 200, 403],      // PIC adalah dosen: dulu terkunci di /admin tanpa jalan kembali ke dasbor dosen
            'admin' => ['admin', 200, 200, 200],
        ];
    }

    #[DataProvider('roles')]
    public function test_panel_access_matrix(string $role, int $admin, int $dosen, int $app): void
    {
        $u = $this->user($role);
        $this->assertSame($admin, $this->actingAs($u)->get('/admin')->status(), "$role -> /admin");
        $this->assertSame($dosen, $this->get('/dosen')->status(), "$role -> /dosen");
        $this->assertSame($app, $this->get('/app')->status(), "$role -> /app");
    }

    #[DataProvider('roles')]
    public function test_admin_panel_links_back_to_the_lecturer_dashboard(string $role, int $admin, int $dosen = 0, int $app = 0): void
    {
        if ($admin !== 200) { $this->markTestSkipped('tidak punya akses panel admin'); }
        $html = $this->actingAs($this->user($role))->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('Dasbor Dosen', $html, "$role melihat tautan ke dasbor dosen");
        $this->assertMatchesRegularExpression('#href="(https?://[^"/]+)?/dosen"#', $html);          // relatif atau absolut
        $this->assertSame($role === 'admin', str_contains($html, 'Panel Mahasiswa'), "$role: tautan Panel Mahasiswa hanya untuk admin");
    }

    public function test_lecturer_panel_links_to_pic_panel(): void
    {
        $html = $this->actingAs($this->user('pic'))->get('/dosen')->assertOk()->getContent();
        $this->assertStringContainsString('Panel PIC', $html);
        $this->assertMatchesRegularExpression('#href="(https?://[^"/]+)?/admin"#', $html);
    }
}
