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

    public function test_ensure_skips_when_route_is_current(): void
    {
        $d = $this->deployment();
        Http::fake(['*' => Http::response(CaddyClient::route($d), 200)]);
        $this->assertFalse((new CaddyClient)->ensure($d));
        Http::assertSentCount(1);
    }
}
