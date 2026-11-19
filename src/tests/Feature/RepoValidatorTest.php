<?php

namespace Tests\Feature;

use App\Models\Innovation;
use App\Models\Repository;
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

    private function validate(array $paths, string $url = 'https://github.com/djambred/dagopark', ?string $tag = null, bool $tagExists = false, bool $branchExists = true, ?string $commit = null, ?string $rawBody = null): array
    {
        static $n = 0;
        $n++;
        $this->seed(DatabaseSeeder::class); // idempoten (updateOrCreate / firstOrCreate)
        config(['fasinova.github_org' => 'djambred']);
        $u = User::create(['name' => 'M', 'email' => "m$n@x.test", 'password' => 'pw', 'role' => 'mahasiswa']);
        $i = Innovation::create(['title' => "Dagopark $n", 'track_id' => Track::where('code', 'fullstack')->value('id'), 'owner_id' => $u->id, 'form_type' => 'web']);
        $i->repository->update(['url' => $url, 'tag' => $tag]);
        Http::swap(new \Illuminate\Http\Client\Factory()); // stub pertama yang cocok menang; mulai dari kosong tiap panggilan
        $notFound = Http::response(['message' => 'Not Found'], 404);
        Http::fake([
            'api.github.com/repos/djambred/dagopark/git/ref/tags/*' => $tagExists ? Http::response(['object' => ['sha' => 'ta9sha', 'type' => 'commit']]) : $notFound,
            'api.github.com/repos/djambred/dagopark/git/ref/heads/*' => $branchExists ? Http::response(['object' => ['sha' => 'c0ffee', 'type' => 'commit']]) : $notFound,
            'api.github.com/repos/djambred/dagopark/commits/*' => $commit ? Http::response(['sha' => $commit]) : $notFound,
            'api.github.com/repos/djambred/dagopark/git/trees/ta9sha*' => Http::response(['tree' => collect($paths)->map(fn ($p) => ['path' => $p])->all()]),
            'api.github.com/repos/djambred/dagopark/git/trees/'.($commit ?: 'zzz').'*' => Http::response(['tree' => collect($paths)->map(fn ($p) => ['path' => $p])->all()]),
            'api.github.com/repos/djambred/dagopark/git/trees/c0ffee*' => Http::response(['tree' => collect($paths)->map(fn ($p) => ['path' => $p])->all()]),
            'api.github.com/repos/djambred/dagopark' => Http::response(['private' => false, 'default_branch' => 'main']),
            'raw.githubusercontent.com/*' => Http::response($rawBody ?? "services:\n  web:\n    image: php:8.3\n"),
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
        $this->assertSame('src/.env.example', $report['env_example']);
        $this->assertTrue($report['ok']);
        $this->assertSame('c0ffee', $report['tag_sha']);
    }

    public function test_committed_env_is_only_a_warning_not_a_blocker(): void
    {
        [$items, $report] = $this->validate(['docker-compose.yml', 'README.md', '.env', 'src/.env.example']);
        $w = $items['.env ter-commit di repo'];
        $this->assertTrue($w['ok']);                                   // tidak menghalangi
        $this->assertTrue($w['warn']);                                 // hanya peringatan
        $this->assertStringContainsString('ditimpa otomatis', $w['detail']);
        $this->assertTrue($report['ok']);                              // validasi tetap lolos
        [$items2] = $this->validate(['docker-compose.yml', 'README.md', 'src/.env', 'src/.env.example']);
        $this->assertTrue($items2['.env ter-commit di repo']['warn']); // .env di subfolder juga terdeteksi
        [$items3] = $this->validate(['docker-compose.yml', 'README.md', 'src/.env.example']);
        $this->assertFalse($items3->has('.env ter-commit di repo'));   // tanpa .env: tidak ada item
    }

    public function test_missing_env_example_everywhere_still_fails(): void
    {
        [$items] = $this->validate(['docker-compose.yml', 'README.md', 'a/b/c/.env.example']); // terlalu dalam (level 3)
        $this->assertFalse($items['File wajib: .env.example']['ok']);
    }

    private const OK = ['docker-compose.yml', 'README.md', 'src/.env.example'];

    public function test_branch_name_in_the_tag_field_is_accepted_as_branch(): void
    {
        [$items, $report] = $this->validate(self::OK, tag: 'main');                 // kasus nyata: mahasiswa mengisi "main" di kolom tag
        $label = $items->keys()->first(fn ($k) => str_starts_with($k, 'Branch main ada'));
        $this->assertNotNull($label, 'branch dikenali walau bukan tag');
        $this->assertTrue($items[$label]['ok']);
        $this->assertSame('branch', $report['ref_type']);
        $this->assertSame('c0ffee', $report['tag_sha']);
        $this->assertTrue($report['ok']);
    }

    public function test_real_tag_still_wins_over_a_branch_of_the_same_name(): void
    {
        [$items, $report] = $this->validate(self::OK, tag: 'v1.0', tagExists: true);
        $this->assertTrue($items['Tag v1.0 ada']['ok']);
        $this->assertSame('tag', $report['ref_type']);
        $this->assertSame('ta9sha', $report['tag_sha']);
    }

    public function test_commit_sha_in_the_tag_field_is_accepted(): void
    {
        [$items, $report] = $this->validate(self::OK, tag: 'abc1234def', branchExists: false, commit: 'abc1234def567');
        $this->assertTrue($items->has('Commit abc1234def ada'));
        $this->assertSame('commit', $report['ref_type']);
        $this->assertSame('abc1234def567', $report['tag_sha']);
        $this->assertTrue($report['ok']);
    }

    public function test_unknown_ref_fails_with_an_actionable_message(): void
    {
        [$items, $report] = $this->validate(self::OK, tag: 'tidak-ada', branchExists: false);
        $it = $items["Tag/branch/commit 'tidak-ada' ada"];
        $this->assertFalse($it['ok']);
        $this->assertStringContainsString('Kosongkan kolom Tag', $it['detail']);       // dulu: gagal tanpa alasan
        $this->assertFalse($report['ok']);
    }

    public function test_url_variants_are_normalised_and_saved_canonically(): void
    {
        foreach ([
            'https://github.com/djambred/dagopark.git',
            'https://github.com/djambred/dagopark/',
            'https://github.com/djambred/dagopark/tree/main/src',          // tautan halaman GitHub yang ditempel mahasiswa
            ' https://github.com/djambred/dagopark.git ',
        ] as $url) {
            [$items] = $this->validate(self::OK, url: $url);
            $this->assertTrue($items['Format URL']['ok'], $url);
            $this->assertSame('https://github.com/djambred/dagopark', Repository::latest('id')->first()->url, $url);
        }
    }

    public function test_branch_from_tree_url_is_used_when_tag_is_empty(): void
    {
        [$items, $report] = $this->validate(self::OK, url: 'https://github.com/djambred/dagopark/tree/main/src');
        $this->assertNotNull($items->keys()->first(fn ($k) => str_starts_with($k, 'Branch main ada')));
        $this->assertSame('branch', $report['ref_type']);
    }

    public function test_clone_url_never_doubles_the_git_suffix(): void
    {
        $C = fn ($u) => \App\Services\Deployer::cloneUrl($u);
        $this->assertSame('https://github.com/o/r.git', $C('https://github.com/o/r'));
        $this->assertSame('https://github.com/o/r.git', $C('https://github.com/o/r.git'));     // dulu menjadi r.git.git
        $this->assertSame('https://github.com/o/r.git', $C('https://github.com/o/r/'));
        $this->assertSame('file:///home/runner/apps/_git/demo.git', $C('file:///home/runner/apps/_git/demo'));
    }

    public function test_stack_is_detected_from_repo_and_overrides_a_wrong_choice(): void
    {
        $base = ['docker-compose.yml', 'README.md', 'src/.env.example'];
        [$items] = $this->validate([...$base, 'src/artisan', 'src/package.json']);          // Laravel juga punya package.json
        $i = Innovation::orderByDesc("id")->first();
        $this->assertSame('laravel', $i->deployment->stack);
        $this->assertSame(8000, (int) $i->deployment->internal_port);
        $this->assertTrue($items->keys()->contains(fn ($k) => str_starts_with($k, 'Stack terdeteksi: Laravel')));

        $i->deployment->update(['stack' => 'streamlit', 'internal_port' => 8501]);       // mahasiswa salah pilih (kasus Trial)
        (new RepoValidator)->validate($i->fresh('repository', 'track'));
        $this->assertSame('laravel', $i->deployment->fresh()->stack);
        $this->assertSame(8000, (int) $i->deployment->fresh()->internal_port);

        $i->deployment->update(['internal_port' => 9000]);                              // port yang diubah sengaja tidak ditimpa
        (new RepoValidator)->validate($i->fresh('repository', 'track'));
        $this->assertSame(9000, (int) $i->deployment->fresh()->internal_port);
    }

    public function test_streamlit_and_js_and_unknown_stacks(): void
    {
        $base = ['docker-compose.yml', 'README.md', '.env.example'];
        $this->validate([...$base, 'requirements.txt'], rawBody: "streamlit==1.30\npandas\n");
        $this->assertSame('streamlit', Innovation::orderByDesc("id")->first()->deployment->stack);
        $this->validate([...$base, 'requirements.txt'], rawBody: "flask\n");                 // Python tapi bukan Streamlit
        $this->assertNull(Innovation::orderByDesc("id")->first()->deployment->stack);
        $this->validate([...$base, 'package.json']);
        $this->assertSame('js', Innovation::orderByDesc("id")->first()->deployment->stack);
        $this->validate($base);                                                              // tak yakin: dibiarkan kosong
        $this->assertNull(Innovation::orderByDesc("id")->first()->deployment->stack);
    }
}
