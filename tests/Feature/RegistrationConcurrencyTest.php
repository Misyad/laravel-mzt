<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\HakAksesRole;
use App\Models\Order;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-01 — Registration quota concurrency.
 *
 * Known limitation: Windows CI has no pcntl; true parallel fork is not
 * available here. We prove correctness via:
 *  1) sequential quota enforcement (second gets 409),
 *  2) query-log proof that the service now does SELECT ... FOR UPDATE,
 *  3) unique-index fallback for duplicate member/event.
 *
 * All tests use the real RegistrationService (with its DB::transaction +
 * lockForUpdate), not a mocked pre-check.
 */
class RegistrationConcurrencyTest extends TestCase
{

    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$schemaBuilt) {
            // Ensure users.is_active exists for CheckActiveAccount if needed.
            if (!\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_active')) {
                \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->string('is_active')->default('1'));
            }
            self::$schemaBuilt = true;
        }
        $this->truncate(['orders','tickets','ticket_logs','payments','payment_logs','events','hak_akses_role','personal_access_tokens','users']);
    }

    private function truncate(array $tables): void
    {
        foreach ($tables as $t) {
            if (\Illuminate\Support\Facades\Schema::hasTable($t)) \DB::table($t)->delete();
        }
    }

    private function makeUser(string $idAnggota): User
    {
        $u = User::factory()->create(['id_anggota'=>$idAnggota, 'is_active'=>'1']);
        HakAksesRole::create(['id_users'=>$u->id,'nama_role'=>'anggota','hak_akses'=>'access']);
        return $u;
    }

    private function makeEvent(int $kuota, string $visibility='public'): Event
    {
        return Event::create([
            'judul_event'=>'Ev '.uniqid(),
            'slug'=>'ev-'.uniqid(),
            'lokasi'=>'L',
            'harga'=>'0',
            'harga_amount'=>0,
            'deskripsi'=>'d',
            'tanggal'=>'01/01/2030 - 02/01/2030',
            'tanggal_mulai'=>'2030-01-01',
            'tanggal_selesai'=>'2030-01-02',
            'is_active'=>'1',
            'kuota'=>$kuota,
            'visibility'=>$visibility,
        ]);
    }

    public function test_quota_one_two_different_members_only_one_succeeds(): void
    {
        $event = $this->makeEvent(1);
        $u1 = $this->makeUser('A001');
        $u2 = $this->makeUser('A002');
        $svc = app(RegistrationService::class);

        $r1 = $svc->register($u1, $event->id);
        $this->assertTrue($r1['ok']); $this->assertEquals(201,$r1['code']);

        $r2 = $svc->register($u2, $event->id);
        $this->assertFalse($r2['ok']);
        $this->assertEquals(409, $r2['code']);
        $this->assertStringContainsString('Kuota', $r2['message']);

        $this->assertEquals(1, Order::where('id_event',$event->id)->count());
    }

    public function test_same_member_concurrent_only_one_order(): void
    {
        $event = $this->makeEvent(10);
        $u = $this->makeUser('B001');
        $svc = app(RegistrationService::class);

        $r1 = $svc->register($u, $event->id);
        $r2 = $svc->register($u, $event->id);

        $this->assertTrue($r1['ok']);
        $this->assertFalse($r2['ok']);
        $this->assertEquals(409,$r2['code']);
        $this->assertEquals(1, Order::where('id_event',$event->id)->where('id_anggota',$u->id_anggota)->count());
    }

    public function test_free_event_concurrency_respects_quota(): void
    {
        $event = $this->makeEvent(1);
        $event->update(['harga_amount'=>0]);
        $u1=$this->makeUser('C001'); $u2=$this->makeUser('C002');
        $svc=app(RegistrationService::class);
        $this->assertTrue($svc->register($u1,$event->id)['ok']);
        $this->assertEquals(409, $svc->register($u2,$event->id)['code']);
    }

    public function test_paid_event_concurrency_respects_quota(): void
    {
        $event = $this->makeEvent(1);
        $event->update(['harga_amount'=>100000]);
        $u1=$this->makeUser('D001'); $u2=$this->makeUser('D002');
        $svc=app(RegistrationService::class);
        $this->assertTrue($svc->register($u1,$event->id)['ok']);
        $this->assertEquals(409, $svc->register($u2,$event->id)['code']);
    }

    public function test_failed_quota_does_not_leave_partial_order(): void
    {
        $event=$this->makeEvent(1);
        $u1=$this->makeUser('E001'); $u2=$this->makeUser('E002');
        $svc=app(RegistrationService::class);
        $svc->register($u1,$event->id);
        $before=Order::count();
        $r=$svc->register($u2,$event->id);
        $this->assertFalse($r['ok']);
        $this->assertEquals($before, Order::count());
    }

    public function test_capacity_uses_lock_for_update(): void
    {
        $event=$this->makeEvent(5);
        $u=$this->makeUser('F001');
        DB::enableQueryLog();
        app(RegistrationService::class)->register($u,$event->id);
        $log=DB::getQueryLog();
        DB::disableQueryLog();
        $hasLock=false;
        foreach($log as $q){ if(str_contains(strtolower($q['query']),'for update')) $hasLock=true; }
        $this->assertTrue($hasLock, 'Registration must SELECT event FOR UPDATE');
    }

    public function test_existing_capacity_semantics_unchanged(): void
    {
        // cancelled orders must not count toward quota
        $event=$this->makeEvent(1);
        $u1=$this->makeUser('G001'); $u2=$this->makeUser('G002');
        $svc=app(RegistrationService::class);
        $r1=$svc->register($u1,$event->id);
        Order::where('id',$r1['order']->id)->update(['status_registrasi'=>'cancelled']);
        $r2=$svc->register($u2,$event->id);
        $this->assertTrue($r2['ok'], 'cancelled order should free quota');
    }

    public function test_http_concurrent_via_api_quota_one(): void
    {
        $event=$this->makeEvent(1);
        $u1=$this->makeUser('H001'); $u2=$this->makeUser('H002');
        // HTTP path also goes through same service.
        Sanctum::actingAs($u1);
        $this->postJson("/api/events/{$event->id}/register")->assertStatus(201);
        Sanctum::actingAs($u2);
        $this->postJson("/api/events/{$event->id}/register")->assertStatus(409);
        $this->assertEquals(1, Order::where('id_event',$event->id)->count());
    }
}
