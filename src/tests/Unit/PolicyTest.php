<?php

namespace Tests\Unit;

use App\Services\ClaimChecker;
use App\Services\ComposePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    public static function bad(): array
    {
        return [
            'privileged' => [['services' => ['a' => ['image' => 'x', 'privileged' => true]]]],
            'docker.sock' => [['services' => ['a' => ['image' => 'x', 'volumes' => ['/var/run/docker.sock:/var/run/docker.sock']]]]],
            'host mount' => [['services' => ['a' => ['image' => 'x', 'volumes' => ['/etc:/etc']]]]],
            'host network' => [['services' => ['a' => ['image' => 'x', 'network_mode' => 'host']]]],
            'cap_add' => [['services' => ['a' => ['image' => 'x', 'cap_add' => ['SYS_ADMIN']]]]],
            'devices' => [['services' => ['a' => ['image' => 'x', 'devices' => ['/dev/sda']]]]],
            'juice-shop' => [['services' => ['a' => ['image' => 'bkimminich/juice-shop:latest']]]],
            'kali' => [['services' => ['a' => ['image' => 'kalilinux/kali-rolling']]]],
            '6 services' => [['services' => array_fill_keys(['a', 'b', 'c', 'd', 'e', 'f'], ['image' => 'x'])]],
        ];
    }

    #[DataProvider('bad')]
    public function test_violations_rejected(array $compose): void
    {
        $this->assertNotEmpty(ComposePolicy::check($compose, ['bkimminich/juice-shop', 'kalilinux/*']));
    }

    public function test_clean_compose_passes_and_hardening_replaces_ports(): void
    {
        $c = ['name' => 'x', 'services' => ['web' => ['image' => 'streamlit', 'ports' => [['target' => 1, 'published' => '80']], 'container_name' => 'evil'],
            'db' => ['image' => 'postgres', 'volumes' => ['data:/var/lib', './init:/init']]]];
        $this->assertSame([], ComposePolicy::check($c, []));
        $h = ComposePolicy::harden($c, 'demo', 'web', 8501, 20001, 0.5, 512);
        $this->assertSame('127.0.0.1', $h['services']['web']['ports'][0]['host_ip']);
        $this->assertSame('20001', $h['services']['web']['ports'][0]['published']);
        $this->assertArrayNotHasKey('ports', $h['services']['db']);
        $this->assertArrayNotHasKey('container_name', $h['services']['web']);
        $this->assertSame(['ALL'], $h['services']['db']['cap_drop']);
        $this->assertEqualsCanonicalizing(config('fasinova.base_caps'), $h['services']['db']['cap_add']); // image resmi bisa chown saat start
    }

    public function test_db_network_is_attached_only_when_requested(): void
    {
        $c = ['services' => ['web' => ['image' => 'x', 'networks' => ['default' => null]], 'worker' => ['image' => 'y']], 'networks' => ['default' => ['name' => 'p_default']]];
        $h = ComposePolicy::harden($c, 's', 'web', 80, 20001, 0.5, 512, [], true);
        $this->assertArrayHasKey('fz-db', $h['services']['web']['networks']);
        $this->assertArrayHasKey('default', $h['services']['web']['networks']);
        $this->assertArrayHasKey('fz-db', $h['services']['worker']['networks']);
        $this->assertTrue($h['networks']['fz-db']['external']);
        $this->assertArrayHasKey('p_default', array_column($h['networks'], 'name', 'name') + ['p_default' => 1]);
        $plain = ComposePolicy::harden($c, 's', 'web', 80, 20001, 0.5, 512);
        $this->assertArrayNotHasKey('fz-db', $plain['services']['web']['networks']);
        $this->assertArrayNotHasKey('fz-db', $plain['networks']);
    }

    public function test_compose_json_roundtrip_keeps_empty_objects(): void
    {
        $raw = '{"name":"x","networks":{"default":{"name":"x_default","ipam":{}}},"services":{"web":{"image":"i","command":null,"cap_add":[],"labels":{}}}}';
        $c = \App\Services\Deployer::decodeCompose($raw);
        $out = json_encode($c);
        $this->assertStringContainsString('"ipam":{}', $out);      // mapping tetap {}
        $this->assertStringContainsString('"labels":{}', $out);
        $this->assertStringContainsString('"cap_add":[]', $out);    // list tetap []
        $this->assertSame('i', $c['services']['web']['image']);
        $h = ComposePolicy::harden($c, 's', 'web', 80, 20001, 0.5, 512);
        $this->assertStringContainsString('"ipam":{}', json_encode($h));
    }

    public function test_public_service_is_detected_from_compose_ports(): void
    {
        // bentuk compose seperti repo monorepo Laravel: php (fpm, tanpa ports), nginx (80/443), db (3306 dipetakan)
        $c = ['services' => [
            'php' => ['image' => 'x'],
            'nginx' => ['ports' => [['target' => 443, 'published' => '443'], ['target' => 80, 'published' => '80']]],
            'db' => ['ports' => [['target' => 3306, 'published' => '13306']]],
        ]];
        $this->assertSame(['nginx', 80], \App\Services\Deployer::detectPublic($c, null, 8000));      // bukan php/db, dan 80 sebelum 443
        $this->assertSame(['nginx', 80], \App\Services\Deployer::detectPublic($c, 'nginx', 8000));
        $this->assertSame(['php', 8000], \App\Services\Deployer::detectPublic($c, 'php', 8000));     // pilihan eksplisit tanpa ports -> port cadangan
        $this->assertSame(['app', 8000], \App\Services\Deployer::detectPublic(['services' => ['app' => ['ports' => ['8000:8000']]]], null, 1));    // bentuk string
        $this->assertSame(['a', 1234], \App\Services\Deployer::detectPublic(['services' => ['a' => ['image' => 'i']]], null, 1234));            // tanpa ports sama sekali
    }

    public function test_extra_caps_are_added_on_top_of_base_without_duplicates(): void
    {
        $h = ComposePolicy::harden(['services' => ['web' => ['image' => 'x']]], 's', 'web', 80, 20001, 0.5, 512, ['NET_BIND_SERVICE', 'CHOWN']);
        $caps = $h['services']['web']['cap_add'];
        $this->assertSame(count($caps), count(array_unique($caps)));
        $this->assertContains('NET_BIND_SERVICE', $caps);
        $this->assertContains('SETUID', $caps);
        $this->assertSame([], ComposePolicy::check(['services' => ['a' => ['image' => 'x', 'cap_add' => ['FOWNER']]]], []));      // diizinkan
        $this->assertNotEmpty(ComposePolicy::check(['services' => ['a' => ['image' => 'x', 'cap_add' => ['SYS_ADMIN']]]], []));   // tetap ditolak
    }

    public function test_claims(): void
    {
        $w = ClaimChecker::warnings(['a' => 'Sistem ini 100% aman dan pasti benar', 'b' => 'hasil 95%'], 'cs');
        $this->assertGreaterThanOrEqual(3, count($w));
        $this->assertSame([], ClaimChecker::warnings(['a' => 'F1 90% pada 200 data uji'], 'cs'));
        $this->assertNotNull(ClaimChecker::blocking(['baseline' => ' '], 'ai'));
        $this->assertNull(ClaimChecker::blocking(['baseline' => 'LogReg'], 'ai'));
    }
}
