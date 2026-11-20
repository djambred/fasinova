<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DeploymentResource\Pages\ListDeployments;
use App\Filament\Admin\Resources\InnovationResource\Pages\ListInnovations;
use App\Jobs\RunnerJob;
use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewDeployTest extends TestCase
{
    use RefreshDatabase;

    private function setUpInnovation(bool $files = true): Innovation
    {
        $this->withoutVite()->seed(DatabaseSeeder::class);
        config(['fasinova.github_org' => 'org']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::where('role', 'admin')->first());
        $owner = User::create(['name' => 'M', 'email' => 'm@x.test', 'password' => 'pw', 'role' => 'mahasiswa']);
        $track = Track::where('code', 'ai')->first();
        $i = Innovation::create(['title' => 'Demo AI', 'track_id' => $track->id, 'owner_id' => $owner->id, 'status' => 'submitted',
            'supervisor_approved_at' => now(), 'consent_ok' => true, 'form_type' => 'web']);
        $i->repository->update(['url' => 'https://github.com/org/ai-demo']);
        $paths = ! $files ? [] : collect($track->required_files)->map(fn ($p) => ['path' => $p])->all();
        Http::fake([
            'api.github.com/repos/org/ai-demo/git/ref/*' => Http::response(['object' => ['sha' => 'abc123', 'type' => 'commit']]),
            'api.github.com/repos/org/ai-demo/git/trees/*' => Http::response(['tree' => $paths]),
            'api.github.com/repos/org/ai-demo' => Http::response(['private' => false]),
            'raw.githubusercontent.com/*' => Http::response("services:\n  web:\n    image: python:3.12\n"),
        ]);
        return $i;
    }

    private function ok(Innovation $i, string $decision): array
    {
        $d = ['decision' => $decision];
        foreach ($i->track->checklist as $n => $_) $d["s_$n"] = 'ok';
        return $d;
    }

    public function test_approve_locks_sha_publishes_and_auto_deploys(): void
    {
        $i = $this->setUpInnovation();
        $i->deployment->update(['stack' => 'streamlit', 'subdomain' => 'demo-ai']);
        Queue::fake();
        Livewire::test(ListInnovations::class)->callTableAction('review', $i, $this->ok($i, 'approve'))->assertHasNoTableActionErrors();
        $i->refresh();
        $this->assertSame('published', $i->status);
        $this->assertNotNull($i->published_at);
        $this->assertSame('abc123', $i->repository->locked_commit_sha);
        Queue::assertPushed(RunnerJob::class, fn ($j) => $j->action === 'deploy' && $j->queue === 'deploy' && $j->deploymentId === $i->deployment->id);
        $this->assertSame('queued', $i->deployment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deploy.auto']);
    }

    public function test_no_auto_deploy_without_stack_and_no_publish_without_consent(): void
    {
        $i = $this->setUpInnovation();
        $i->update(['consent_ok' => false]);
        Queue::fake();
        Livewire::test(ListInnovations::class)->callTableAction('review', $i, $this->ok($i, 'approve'));
        $this->assertSame('approved', $i->fresh()->status);   // formulir persetujuan belum ada -> belum terbit
        Queue::assertNotPushed(RunnerJob::class);                          // stack belum dipilih -> tidak deploy
    }

    public function test_non_web_forms_are_published_but_never_auto_deployed(): void
    {
        $i = $this->setUpInnovation();
        $i->update(['form_type' => 'mobile']);
        $i->deployment->update(['stack' => 'streamlit', 'subdomain' => 'demo-ai']); // sisa data tidak boleh memicu deploy
        Queue::fake();
        Livewire::test(ListInnovations::class)->callTableAction('review', $i, $this->ok($i, 'approve'));
        $this->assertSame('published', $i->fresh()->status);
        Queue::assertNotPushed(RunnerJob::class);
    }

    public function test_cyber_security_only_deploys_when_pic_marks_safe(): void
    {
        $this->setUpInnovation();
        $cs = Track::where('code', 'cs')->first();
        $mk = function () use ($cs) {
            $i = Innovation::create(['title' => 'CS '.uniqid(), 'track_id' => $cs->id, 'owner_id' => User::where('role', 'mahasiswa')->value('id') ?? User::where('role', 'admin')->value('id'),
                'status' => 'submitted', 'supervisor_approved_at' => now(), 'consent_ok' => true, 'form_type' => 'web']);
            $i->repository->update(['url' => 'https://github.com/org/cs-demo']);
            $i->deployment->update(['stack' => 'streamlit']);
            return $i;
        };
        Http::fake([
            'api.github.com/repos/org/cs-demo/git/ref/*' => Http::response(['object' => ['sha' => 'cs1', 'type' => 'commit']]),
            'api.github.com/repos/org/cs-demo/git/trees/*' => Http::response(['tree' => collect($cs->required_files)->map(fn ($p) => ['path' => $p])->all()]),
            'api.github.com/repos/org/cs-demo' => Http::response(['private' => false]),
            'raw.githubusercontent.com/*' => Http::response("services:\n  web:\n    image: python:3.12\n"),
        ]);
        Queue::fake();
        $a = $mk(); $b = $mk();
        $d = fn ($i, $safe) => $this->ok($i, 'approve') + ['safe_ok' => $safe];
        Livewire::test(ListInnovations::class)->callTableAction('review', $a, $d($a, false));
        Queue::assertNotPushed(RunnerJob::class);
        $this->assertFalse((bool) $a->fresh()->safe_service_ok);
        Livewire::test(ListInnovations::class)->callTableAction('review', $b, $d($b, true));
        Queue::assertPushed(RunnerJob::class, 1);
        $this->assertTrue((bool) $b->fresh()->safe_service_ok);
    }

    public function test_pic_can_still_trigger_manual_redeploy_and_reserved_subdomain_is_rejected(): void
    {
        $i = $this->setUpInnovation();
        $i->update(['status' => 'approved']);
        Queue::fake();
        Livewire::test(ListInnovations::class)->callTableAction('deploy', $i, ['subdomain' => 'admin', 'internal_port' => 8501, 'health_path' => '/'])->assertHasTableActionErrors();
        Livewire::test(ListInnovations::class)->callTableAction('deploy', $i, ['subdomain' => 'demo-ai', 'internal_port' => 8501, 'health_path' => '/'])->assertHasNoTableActionErrors();
        Queue::assertPushed(RunnerJob::class, fn ($j) => $j->action === 'deploy');
        $this->assertSame('demo-ai', $i->deployment->fresh()->subdomain);
    }

    public function test_repo_without_tag_uses_default_branch_and_locks_its_sha(): void
    {
        $i = $this->setUpInnovation();
        $i->repository->update(['tag' => null]);
        $i->deployment->update(['stack' => 'streamlit', 'subdomain' => 'demo-ai']);
        $paths = collect($i->track->required_files)->map(fn ($p) => ['path' => $p])->all();
        Http::swap(new \Illuminate\Http\Client\Factory()); // buang stub fixture (stub pertama yang cocok menang)
        Http::fake([
            'api.github.com/repos/org/ai-demo/git/ref/heads/trunk' => Http::response(['object' => ['sha' => 'branchsha1', 'type' => 'commit']]),
            'api.github.com/repos/org/ai-demo/git/trees/branchsha1*' => Http::response(['tree' => $paths]),
            'api.github.com/repos/org/ai-demo' => Http::response(['private' => false, 'default_branch' => 'trunk']),
            'raw.githubusercontent.com/*' => Http::response("services:\n  web:\n    image: python:3.12\n"),
        ]);
        Queue::fake();
        Livewire::test(ListInnovations::class)->callTableAction('review', $i, $this->ok($i, 'approve'));
        $i->refresh();
        $this->assertSame('published', $i->status);
        $this->assertSame('branchsha1', $i->repository->locked_commit_sha);        // SHA branch default terkunci
        $items = collect($i->repository->validation_report['items'] ?? []);
        $this->assertTrue($items->contains(fn ($x) => str_contains($x['label'], 'Branch default (trunk)')));
        Queue::assertPushed(RunnerJob::class, fn ($j) => $j->action === 'deploy');
    }

    public function test_return_sets_seven_day_deadline(): void
    {
        $i = $this->setUpInnovation();
        $t = Livewire::test(ListInnovations::class);
        $t->callTableAction('review', $i, $this->ok($i, 'return'));
        $i->refresh();
        $this->assertSame('needs_revision', $i->status);
        $this->assertEqualsWithDelta(7, now()->diffInDays($i->revision_due_at, true), 0.1);

    }

    public function test_missing_required_files_block_approval(): void
    {
        $i = $this->setUpInnovation(files: false);
        Livewire::test(ListInnovations::class)->callTableAction('review', $i, $this->ok($i, 'approve'));
        $this->assertSame('submitted', $i->fresh()->status);
        $this->assertNull($i->repository->fresh()->locked_commit_sha);
    }

    public function test_deploy_actions_hidden_until_pic_has_locked_the_sha(): void
    {
        $i = $this->setUpInnovation();
        $dep = $i->deployment;
        $dep->update(['stack' => 'laravel', 'status' => 'failed']);   // daftar hanya memuat status selain not_deployed
        Livewire::test(ListDeployments::class)->assertTableActionHidden('redeploy', $dep)->assertTableActionHidden('start', $dep);   // dulu: Redeploy → "SHA belum dikunci" → status failed
        $i->repository->update(['locked_commit_sha' => 'abc123']);
        Livewire::test(ListDeployments::class)->assertTableActionVisible('redeploy', $dep);
    }

    public function test_redeploy_during_running_deploy_keeps_its_status_and_logs(): void
    {
        Queue::fake();
        $i = $this->setUpInnovation();
        $i->repository->update(['locked_commit_sha' => 'abc123']);
        $dep = $i->deployment;
        $dep->update(['stack' => 'laravel', 'status' => 'building']);
        $dep->logs()->create(['step' => 'building', 'stream' => 'out', 'line' => 'x']);
        Livewire::test(ListDeployments::class)->callTableAction('redeploy', $dep);
        $this->assertSame('building', $dep->fresh()->status);     // dulu tertimpa 'queued' padahal sedang building
        $this->assertSame(1, $dep->logs()->count());
        Queue::assertPushed(RunnerJob::class, 1);                 // deploy berikutnya tetap masuk antrean

        $dep->update(['status' => 'live']);
        Livewire::test(ListDeployments::class)->callTableAction('redeploy', $dep);
        $this->assertSame('queued', $dep->fresh()->status);
    }
}
