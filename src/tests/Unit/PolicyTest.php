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

    public function test_app_that_handles_its_own_tls_is_detected(): void
    {
        $D = \App\Services\Deployer::class;
        $this->assertTrue($D::redirectsToHttps(301, 'https://dagopark-uji.fasinova.my.id/'));
        $this->assertTrue($D::redirectsToHttps(308, 'HTTPS://x/'));
        $this->assertFalse($D::redirectsToHttps(301, 'http://x/'));        // redirect ke http bukan sinyal TLS
        $this->assertFalse($D::redirectsToHttps(200, 'https://x/'));       // bukan redirect
        $this->assertFalse($D::redirectsToHttps(null, ''));
        $this->assertFalse($D::redirectsToHttps(0, ''));                   // tidak ada respons
        $compose = ['services' => ['nginx' => ['ports' => [['target' => 443, 'published' => '443'], ['target' => 80, 'published' => '80']]],
            'web' => ['ports' => ['8080:80/tcp', '127.0.0.1:8443:443']], 'php' => ['image' => 'x']]];
        $this->assertEqualsCanonicalizing([443, 80], $D::declaredTargets($compose, 'nginx'));
        $this->assertEqualsCanonicalizing([80, 443], $D::declaredTargets($compose, 'web'));    // bentuk string, dengan /tcp dan host ip
        $this->assertSame([], $D::declaredTargets($compose, 'php'));
        $this->assertSame([], $D::declaredTargets($compose, 'tidak-ada'));
    }

    public function test_env_is_passed_as_container_environment_and_beats_repo_values(): void
    {
        $c = ['services' => ['php' => ['image' => 'x', 'environment' => ['PROJECT_NAME' => 'dagopark', 'APP_URL' => 'https://dagopark.test']],
            'nginx' => ['image' => 'y']]];
        $h = ComposePolicy::harden($c, 's', 'nginx', 80, 20001, 0.5, 512, [], false, ['APP_URL' => 'https://s.fasinova.my.id', 'APP_KEY' => 'base64:ab$cd']);
        $this->assertSame('https://s.fasinova.my.id', $h['services']['php']['environment']['APP_URL']);   // nilai FASINOVA menang
        $this->assertSame('dagopark', $h['services']['php']['environment']['PROJECT_NAME']);             // milik repo yang lain tetap
        $this->assertSame('base64:ab$$cd', $h['services']['php']['environment']['APP_KEY']);            // $ dilipat agar tak diinterpolasi ulang
        $this->assertSame('https://s.fasinova.my.id', $h['services']['nginx']['environment']['APP_URL']); // service tanpa environment juga dapat
        $plain = ComposePolicy::harden($c, 's', 'nginx', 80, 20001, 0.5, 512);                          // tanpa env: perilaku lama
        $this->assertArrayNotHasKey('APP_KEY', $plain['services']['php']['environment']);
        $this->assertArrayNotHasKey('environment', $plain['services']['nginx']);
    }

    public function test_env_map_defaults_public_url_and_db_overrides_student_values(): void
    {
        $v = fn (string $k, string $val) => (object) ['key' => $k, 'value' => $val];
        $D = \App\Services\Deployer::class;
        $map = $D::envMap(collect([$v('APP_NAME', 'X'), $v('DB_HOST', 'milik-mahasiswa'), $v('ASSET_URL', '')]), ['DB_HOST' => 'karya-mariadb', 'DB_PORT' => '3306'], 'https://s.fasinova.my.id');
        $this->assertSame('https://s.fasinova.my.id', $map['APP_URL']);          // tidak diisi mahasiswa -> alamat publik
        $this->assertSame('https://s.fasinova.my.id', $map['ASSET_URL']);        // kosong dianggap tidak diisi
        $this->assertSame('karya-mariadb', $map['DB_HOST']);                      // DB dari FASINOVA menang
        $this->assertSame('X', $map['APP_NAME']);
        $own = $D::envMap(collect([$v('APP_URL', 'https://domain-sendiri.example')]), [], 'https://s.fasinova.my.id');
        $this->assertSame('https://domain-sendiri.example', $own['APP_URL']);    // pilihan eksplisit mahasiswa dihormati
        $this->assertSame("A=\"1\\\"2\\\$3\"\nB=\"x\"\n", $D::envText(['A' => '1"2$3', 'B' => 'x']));   // kutip dan $ di-escape
    }

    public function test_compose_ps_parsing_supports_ndjson_and_array(): void
    {
        $D = \App\Services\Deployer::class;
        $nd = "{\"Service\":\"nginx\",\"State\":\"running\"}\n{\"Service\":\"php\",\"State\":\"Restarting\"}\n{\"Service\":\"db\",\"State\":\"exited\"}\n";
        $this->assertSame(['nginx' => 'running', 'php' => 'restarting', 'db' => 'exited'], $D::parseComposePs($nd));
        $arr = '[{"Service":"web","State":"running"}]';
        $this->assertSame(['web' => 'running'], $D::parseComposePs($arr));
        $this->assertSame([], $D::parseComposePs(''));
        $this->assertSame([], $D::parseComposePs('bukan json'));
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
