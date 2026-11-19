<?php

namespace Tests\Feature;

use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use App\Services\RepoValidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepoValidatorTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $paths): array
    {
        static $n = 0;
        $n++;
        $this->seed(DatabaseSeeder::class); // idempoten (updateOrCreate / firstOrCreate)
        config(['fasinova.github_org' => 'djambred']);
        $u = User::create(['name' => 'M', 'email' => "m$n@x.test", 'password' => 'pw', 'role' => 'mahasiswa']);
        $i = Innovation::create(['title' => "Dagopark $n", 'track_id' => Track::where('code', 'fullstack')->value('id'), 'owner_id' => $u->id, 'form_type' => 'web']);
        $i->repository->update(['url' => 'https://github.com/djambred/dagopark', 'tag' => null]);
        Http::fake([
            'api.github.com/repos/djambred/dagopark/git/ref/heads/main' => Http::response(['object' => ['sha' => 'c0ffee', 'type' => 'commit']]),
            'api.github.com/repos/djambred/dagopark/git/trees/c0ffee*' => Http::response(['tree' => collect($paths)->map(fn ($p) => ['path' => $p])->all()]),
            'api.github.com/repos/djambred/dagopark' => Http::response(['private' => false, 'default_branch' => 'main']),
            'raw.githubusercontent.com/*' => Http::response("services:\n  web:\n    image: php:8.3\n"),
        ]);
        $items = (new RepoValidator)->validate($i->fresh('repository', 'track'));
        return [collect($items)->keyBy('label'), $i->repository->fresh()->validation_report];
    }

    public function test_monorepo_env_example_in_subfolder_counts_and_is_remembered(): void
    {
        [$items, $report] = $this->validate(['docker-compose.yml', 'README.md', 'php/Dockerfile', 'src/.env.example', 'src/composer.json']);
        $this->assertTrue($items['File wajib: .env.example']['ok']);
        $this->assertTrue($items['File wajib: Dockerfile|docker-compose.yml']['ok']);
        $this->assertTrue($items['File wajib: README.md']['ok']);
        $this->assertTrue($items['Tidak ada .env ter-commit']['ok']);
        $this->assertSame('src/.env.example', $report['env_example']);
        $this->assertTrue($report['ok']);
        $this->assertSame('c0ffee', $report['tag_sha']);
    }

    public function test_committed_env_fails_validation_early(): void
    {
        [$items, $report] = $this->validate(['docker-compose.yml', 'README.md', '.env', 'src/.env.example']);
        $this->assertFalse($items['Tidak ada .env ter-commit']['ok']);
        $this->assertStringContainsString('.env ada di repo', $items['Tidak ada .env ter-commit']['detail']);
        $this->assertFalse($report['ok']);
        [$items2] = $this->validate(['docker-compose.yml', 'README.md', 'src/.env']); // .env di subfolder juga terdeteksi
        $this->assertFalse($items2['Tidak ada .env ter-commit']['ok']);
    }

    public function test_missing_env_example_everywhere_still_fails(): void
    {
        [$items] = $this->validate(['docker-compose.yml', 'README.md', 'a/b/c/.env.example']); // terlalu dalam (level 3)
        $this->assertFalse($items['File wajib: .env.example']['ok']);
    }
}
