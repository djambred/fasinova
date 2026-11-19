<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyOverdue;
use App\Filament\Admin\Resources\InnovationResource\Pages\ListInnovations as AdminList;
use App\Filament\Dosen\Resources\BimbinganResource\Pages\ListBimbingan;
use App\Models\Innovation;
use App\Models\Track;
use App\Models\User;
use App\Support\Notify;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->withoutVite()->seed(DatabaseSeeder::class);
        $ai = Track::where('code', 'ai')->first();
        $mk = fn ($role, $x = []) => User::create(['name' => $role, 'email' => $role.rand().'@x.test', 'password' => 'pw', 'role' => $role] + $x);
        $owner = $mk('mahasiswa', ['nim' => '1']);
        $member = $mk('mahasiswa', ['nim' => '2']);
        $sup = $mk('dosen', ['kode_dosen' => 'D1']);
        $pic = $mk('pic'); $pic->tracksAsPic()->attach($ai);
        $i = Innovation::create(['title' => 'Karya A', 'track_id' => $ai->id, 'owner_id' => $owner->id, 'supervisor_id' => $sup->id,
            'status' => 'submitted', 'members' => [['nim' => '2', 'name' => 'M']]]);
        return [$i, $owner, $member, $sup, $pic];
    }

    private function got(User $u, string $needle): int
    {
        return $u->notifications()->get()->filter(fn ($n) => str_contains(json_encode($n->data), $needle))->count();
    }

    public function test_supervisor_approval_notifies_whole_team(): void
    {
        [$i, $owner, $member, $sup] = $this->fixture();
        $i->update(['status' => 'draft']);
        Filament::setCurrentPanel('dosen');
        $this->actingAs($sup);
        Livewire::test(ListBimbingan::class)->callTableAction('approve', $i);
        $this->assertSame(1, $this->got($owner, 'Pembimbing menyetujui'));
        $this->assertSame(1, $this->got($member, 'Pembimbing menyetujui'));
        $this->assertSame(0, $this->got($sup, 'Pembimbing menyetujui'));
    }

    public function test_return_notifies_team_and_supervisor_with_deadline(): void
    {
        [$i, $owner, $member, $sup, $pic] = $this->fixture();
        Filament::setCurrentPanel('admin');
        $this->actingAs($pic);
        $d = ['decision' => 'return'];
        foreach ($i->track->checklist as $n => $_) $d["s_$n"] = 'ok';
        Livewire::test(AdminList::class)->callTableAction('review', $i, $d);
        foreach ([$owner, $member, $sup] as $u) $this->assertSame(1, $this->got($u, 'dikembalikan'), $u->role);
        $this->assertSame(0, $this->got($pic, 'dikembalikan'));
        $this->assertSame(1, $owner->unreadNotifications()->count());
    }

    public function test_pics_fall_back_to_admin_and_inquiry_notifies_admin(): void
    {
        [$i, , , , $pic] = $this->fixture();
        $pics = Notify::pics($i);
        $this->assertTrue($pics->contains($pic));                          // PIC peminatan
        $this->assertTrue($pics->contains($i->supervisor));                // pembimbing ikut sebagai PIC lingkup karya
        $pic->tracksAsPic()->detach();
        $i->update(['supervisor_id' => null]);                              // tak ada PIC peminatan, pembimbing, kaprodi, maupun dekan
        $this->assertTrue(Notify::pics($i->fresh('track'))->every(fn ($u) => $u->role === 'admin'));   // baru jatuh ke admin

        $i->update(['status' => 'published', 'slug' => 'karya-a']);
        $this->post('/karya/karya-a/kolaborasi', ['name' => 'Budi', 'email' => 'b@x.co', 'message' => 'hai', 'pdp' => 1])->assertRedirect();
        $this->assertSame(1, $this->got(User::where('role', 'admin')->first(), 'Inquiry kolaborasi baru'));
    }

    public function test_overdue_command_notifies_once_in_window(): void
    {
        [$i, $owner, , $sup, $pic] = $this->fixture();
        $i->update(['status' => 'needs_revision', 'revision_due_at' => now()->subHours(2)]);
        $this->artisan('fasinova:overdue')->assertSuccessful();
        foreach ([$owner, $sup, $pic] as $u) $this->assertSame(1, $this->got($u, 'terlambat'), $u->role);
        $i->update(['revision_due_at' => now()->subDays(3)]);
        $this->artisan('fasinova:overdue');
        $this->assertSame(1, $this->got($owner, 'terlambat'));
    }
}
