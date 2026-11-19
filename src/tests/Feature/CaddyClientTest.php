<?php

namespace Tests\Feature;

use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use App\Services\CaddyClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaddyClientTest extends TestCase
{
    use RefreshDatabase;

    private function deployment()
    {
        $this->seed(DatabaseSeeder::class);
        config(['fasinova.domain' => 'fasinova.id']);
        $u = User::create(['name' => 'M', 'email' => 'm@x.test', 'password' => 'pw', 'role' => 'mahasiswa']);
        $i = Innovation::create(['title' => 'Demo Satu', 'track_id' => Track::where('code', 'ai')->value('id'), 'owner_id' => $u->id]);
        $i->deployment->update(['host_port' => 20007, 'subdomain' => 'demo-satu']);
        return $i->deployment->fresh();
    }

    public function test_route_shape(): void
    {
        $d = $this->deployment();
        $r = CaddyClient::route($d);
        $this->assertSame('fz-'.$d->id, $r['@id']);
        $this->assertSame(['demo-satu.fasinova.id'], $r['match'][0]['host']);
        $this->assertSame('127.0.0.1:20007', $r['handle'][0]['upstreams'][0]['dial']);
    }

    public function test_upsert_deletes_then_inserts_in_front_of_catch_all(): void
    {
        $d = $this->deployment();
        // delete 404 diabaikan; put harus 2xx
        Http::fake(['*/id/fz-server/routes/0' => Http::response('', 200), '*/id/fz-*' => Http::response('', 404)]);
        (new CaddyClient)->upsert($d);
        Http::assertSentInOrder([
            fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/id/fz-'.$d->id),
            fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/id/fz-server/routes/0') && $r['@id'] === 'fz-'.$d->id,
        ]);
    }

    public function test_tombstone_replaces_route_with_404_on_same_id(): void
    {
        $d = $this->deployment();
        Http::fake(['*/id/fz-server/routes/0' => Http::response('', 200), '*/id/fz-*' => Http::response('', 404)]);
        (new CaddyClient)->tombstone($d);
        Http::assertSentInOrder([
            fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/id/fz-'.$d->id),
            fn (Request $r) => $r->method() === 'PUT' && $r['@id'] === 'fz-'.$d->id && $r['handle'][0]['handler'] === 'static_response'
                && $r['handle'][0]['status_code'] === 404 && $r['match'][0]['host'] === ['demo-satu.fasinova.id'],
        ]);
    }

    public function test_tls_upstream_route_skips_verification_only_when_flagged(): void
    {
        $d = $this->deployment();
        $plain = CaddyClient::route($d);
        $this->assertArrayNotHasKey('transport', $plain['handle'][0]);
        $d->update(['upstream_tls' => true]);
        $tls = CaddyClient::route($d->fresh());
        $this->assertTrue($tls['handle'][0]['transport']['tls']['insecure_skip_verify']);
        $this->assertSame('127.0.0.1:20007', $tls['handle'][0]['upstreams'][0]['dial']);
    }

    public function test_ensure_reinstalls_route_when_tls_mode_changed(): void
    {
        $d = $this->deployment();
        $old = CaddyClient::route($d);                               // rute lama (tanpa TLS) masih terpasang di Caddy
        $d->update(['upstream_tls' => true]);
        Http::fake(['*/id/fz-*' => Http::sequence()->push($old, 200)->push('', 404)->push('', 200),   // GET lama, DELETE, PUT baru
                    '*/id/fz-server/routes/0' => Http::response('', 200)]);
        $this->assertTrue((new CaddyClient)->ensure($d->fresh()));   // mode berubah -> dipasang ulang
    }

    public function test_ensure_skips_when_route_is_current(): void
    {
        $d = $this->deployment();
        Http::fake(['*' => Http::response(CaddyClient::route($d), 200)]);
        $this->assertFalse((new CaddyClient)->ensure($d));
        Http::assertSentCount(1);
    }
}
