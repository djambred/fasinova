<?php

namespace Tests\Feature;

use App\Jobs\HealthSweep;
use App\Models\Deployment;
use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use App\Services\ComposePolicy;
use App\Services\Deployer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeployerSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/fz-safety-'.uniqid();
        mkdir($this->tmp.'/clone/src', 0777, true);
        mkdir($this->tmp.'/victim', 0777, true);
        file_put_contents($this->tmp.'/victim/secret.txt', "ASLI\n");
        chmod($this->tmp.'/victim/secret.txt', 0600);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    public function test_symlinks_leaving_the_clone_are_removed_but_internal_ones_stay(): void
    {
        $c = $this->tmp.'/clone';
        symlink($this->tmp.'/victim', "$c/data");                 // keluar folder clone
        symlink($this->tmp.'/victim/secret.txt', "$c/.env");     // keluar
        symlink('/nonexistent/target', "$c/src/dangling");       // menggantung
        file_put_contents("$c/src/real.txt", 'ok');
        symlink("$c/src/real.txt", "$c/alias.txt");              // di dalam folder clone: boleh
        mkdir("$c/.git");
        symlink('/etc', "$c/.git/biarkan");                      // .git tidak diperiksa (bukan tempat Docker mem-build/mount)

        $removed = Deployer::stripEscapingSymlinks($c);

        sort($removed);
        $this->assertSame(['.env', 'data', 'src/dangling'], $removed);
        $this->assertFalse(is_link("$c/data"));
        $this->assertTrue(is_link("$c/alias.txt"));
        $this->assertSame('ASLI', trim(file_get_contents($this->tmp.'/victim/secret.txt')));   // korban tidak tersentuh
    }

    public function test_writeFile_never_writes_through_a_symlink(): void
    {
        $link = $this->tmp.'/clone/.env';
        symlink($this->tmp.'/victim/secret.txt', $link);
        Deployer::writeFile($link, "PATH=\"/tmp/evil\"\n", 0644);

        $this->assertSame('ASLI', trim(file_get_contents($this->tmp.'/victim/secret.txt'))); // isi korban utuh
        $this->assertSame(0600, fileperms($this->tmp.'/victim/secret.txt') & 0777);          // mode korban utuh
        $this->assertFalse(is_link($link));
        $this->assertSame("PATH=\"/tmp/evil\"\n", file_get_contents($link));                 // berkas biasa di dalam clone
    }

    public function test_compose_policy_rejects_bind_mount_through_escaping_symlink(): void
    {
        $c = $this->tmp.'/clone';
        symlink($this->tmp.'/victim', "$c/data");
        mkdir("$c/real");
        $root = realpath($c);
        $compose = fn (string $src) => ['services' => ['web' => ['image' => 'x', 'volumes' => [['type' => 'bind', 'source' => $src, 'target' => '/x']]]]];

        $bad = ComposePolicy::check($compose("$root/data"), [], $root);          // teks path "di dalam", tujuan sebenarnya di luar
        $this->assertNotEmpty($bad);
        $this->assertStringContainsString('symlink', $bad[0]);
        $this->assertSame([], ComposePolicy::check($compose("$root/real"), [], $root));   // folder biasa tetap boleh
    }

    private function liveDeployment(string $slug, int $failures): Deployment
    {
        $u = User::firstOrCreate(['email' => 'hs@x.test'], ['name' => 'H', 'password' => 'pw', 'role' => 'mahasiswa']);
        $i = Innovation::create(['title' => $slug, 'track_id' => Track::where('code', 'ai')->value('id'), 'owner_id' => $u->id, 'form_type' => 'web']);
        $i->deployment->update(['status' => 'live', 'host_port' => 20000 + Deployment::count(), 'health_failures' => $failures, 'public_service' => null]);
        return $i->deployment->fresh();
    }

    public function test_one_broken_stack_does_not_stop_the_health_sweep(): void
    {
        $this->seed(DatabaseSeeder::class);
        Http::fake(['*' => Http::response('', 500)]);                       // semua demo tidak sehat
        $a = $this->liveDeployment('stack rusak', 2);                       // ke-3 kali gagal -> stop() -> melempar (folder/compose tidak ada)
        $b = $this->liveDeployment('stack lain', 0);

        (new HealthSweep)->handle(app(Deployer::class));                     // tidak boleh melempar

        $this->assertSame(1, $b->fresh()->health_failures, 'stack setelah yang rusak tetap diperiksa');
        $this->assertSame(3, $a->fresh()->health_failures);
        $this->assertSame('live', $a->fresh()->status);                      // stop gagal -> status tidak dipalsukan
    }

    public function test_health_and_caddy_jobs_use_the_ops_queue_not_the_deploy_queue(): void
    {
        $this->assertSame('ops', (new HealthSweep)->queue);
        $this->assertSame('ops', (new \App\Jobs\CaddySync)->queue);
        $this->assertSame('deploy', (new \App\Jobs\RunnerJob(1, 'deploy'))->queue);
        $this->assertNotEmpty((new HealthSweep)->middleware());
        $this->assertNotEmpty((new \App\Jobs\RunnerJob(1, 'stop'))->middleware());
    }

    public function test_duplicate_deploy_for_same_stack_is_queued_once(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        \App\Jobs\RunnerJob::dispatch(1, 'deploy');
        \App\Jobs\RunnerJob::dispatch(1, 'deploy');   // approve + redeploy manual / klik ganda
        \App\Jobs\RunnerJob::dispatch(1, 'stop');     // aksi lain tetap masuk
        \App\Jobs\RunnerJob::dispatch(2, 'deploy');   // stack lain tetap masuk
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RunnerJob::class, 3);
    }
}
