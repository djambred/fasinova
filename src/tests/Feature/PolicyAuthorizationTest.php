<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\Faculty;
use App\Models\Innovation;
use App\Models\StudyProgram;
use App\Models\Track;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $role, array $x = []): User
    {
        static $n = 0;
        return User::create(['name' => $role, 'email' => $role.++$n.'@x.test', 'password' => 'pw', 'role' => $role] + $x);
    }

    private function can(User $u, string $ability, $model): bool { return Gate::forUser($u)->allows($ability, $model); }

    public function test_innovation_rules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ai = Track::where('code', 'ai')->first();
        $cs = Track::where('code', 'cs')->first();
        $f = Faculty::create(['code' => 'F', 'name' => 'F']);
        $prodi = StudyProgram::create(['faculty_id' => $f->id, 'code' => 'P', 'name' => 'P']);

        $owner = $this->u('mahasiswa', ['nim' => '111', 'study_program_id' => $prodi->id]);
        $member = $this->u('mahasiswa', ['nim' => '222']);
        $other = $this->u('mahasiswa', ['nim' => '333']);
        $sup = $this->u('dosen', ['kode_dosen' => 'D1']);
        $kaprodi = $this->u('dosen', ['kode_dosen' => 'D2']);
        $prodi->update(['head_id' => $kaprodi->id]);
        $pic = $this->u('pic'); $pic->tracksAsPic()->attach($ai);
        $picCs = $this->u('pic'); $picCs->tracksAsPic()->attach($cs);
        $admin = User::where('role', 'admin')->first();

        $i = Innovation::create(['title' => 'X', 'track_id' => $ai->id, 'owner_id' => $owner->id, 'supervisor_id' => $sup->id,
            'status' => 'draft', 'members' => [['nim' => '222', 'name' => 'M']]]);

        // lihat
        foreach ([$owner, $member, $sup, $kaprodi, $pic, $admin] as $ok) $this->assertTrue($this->can($ok, 'view', $i), $ok->role);
        foreach ([$other, $picCs] as $no) $this->assertFalse($this->can($no, 'view', $i), $no->role);
        // edit: tim saat draft; dosen/PIC tidak; terkunci setelah dikirim
        $this->assertTrue($this->can($member, 'update', $i));
        $this->assertFalse($this->can($sup, 'update', $i));
        $this->assertFalse($this->can($pic, 'update', $i));
        $i->update(['status' => 'submitted']);
        $this->assertFalse($this->can($owner, 'update', $i));
        $this->assertFalse($this->can($owner, 'delete', $i));
        // periksa: hanya PIC peminatan itu / admin
        $this->assertTrue($this->can($pic, 'review', $i));
        $this->assertFalse($this->can($picCs, 'review', $i));
        $this->assertFalse($this->can($sup, 'review', $i));
        $this->assertFalse($this->can($owner, 'review', $i));
        // deploy/terbit
        foreach (['deploy', 'publish', 'markSafe'] as $a) {
            $this->assertTrue($this->can($pic, $a, $i)); $this->assertTrue($this->can($admin, $a, $i));
            foreach ([$owner, $sup, $kaprodi, $picCs] as $no) $this->assertFalse($this->can($no, $a, $i), "$a {$no->role}");
        }
        // persetujuan pembimbing: hanya pembimbing itu, sekali saja
        $this->assertTrue($this->can($sup, 'approveAsSupervisor', $i));
        $this->assertFalse($this->can($kaprodi, 'approveAsSupervisor', $i));
        $i->update(['supervisor_approved_at' => now()]);
        $this->assertFalse($this->can($sup, 'approveAsSupervisor', $i));
        // hanya mahasiswa membuat karya
        $this->assertTrue(Gate::forUser($owner)->allows('create', Innovation::class));
        $this->assertFalse(Gate::forUser($sup)->allows('create', Innovation::class));
        $this->assertFalse(Gate::forUser($pic)->allows('create', Innovation::class));
    }

    public function test_deployment_and_admin_only_rules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $ai = Track::where('code', 'ai')->first();
        $pic = $this->u('pic'); $pic->tracksAsPic()->attach($ai);
        $picOther = $this->u('pic');
        $admin = User::where('role', 'admin')->first();
        $mhs = $this->u('mahasiswa', ['nim' => '9']);
        $i = Innovation::create(['title' => 'D', 'track_id' => $ai->id, 'owner_id' => $mhs->id]);
        $d = $i->deployment;

        $this->assertTrue($this->can($pic, 'update', $d));
        $this->assertFalse($this->can($picOther, 'update', $d));
        $this->assertFalse($this->can($mhs, 'view', $d));
        $this->assertFalse(Gate::forUser($admin)->allows('create', Deployment::class));

        foreach ([User::class, Track::class, Faculty::class, StudyProgram::class] as $m) {
            $this->assertTrue(Gate::forUser($admin)->allows('viewAny', $m));
            foreach ([$pic, $mhs] as $no) $this->assertFalse(Gate::forUser($no)->allows('viewAny', $m), $m);
        }
        $this->assertFalse(Gate::forUser($admin)->allows('delete', \App\Models\AuditLog::create(['action' => 'x'])));
        $this->assertFalse(Gate::forUser($pic)->allows('manage-settings'));
        $this->assertTrue(Gate::forUser($admin)->allows('manage-settings'));
    }
}
