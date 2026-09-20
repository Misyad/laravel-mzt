<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\HakAksesRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentVerificationQueueTest extends TestCase
{

    private static bool $schemaBuilt = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$schemaBuilt) {
            $this->buildSchema();
            self::$schemaBuilt = true;
        }
        $this->truncate(['payment_logs','payment_proofs','payments','orders','events','hak_akses_role','personal_access_tokens','users']);
        $this->seedActiveRoleCatalog(['anggota', 'dashboard', 'event', 'finance', 'prisensi', 'ketua', 'admin']);
    }

    private function buildSchema(): void
    {
        // Ensure users.is_active exists (CheckActiveAccount) – add if missing.
        if (!Schema::hasColumn('users','is_active')) {
            Schema::table('users', function ($table) { $table->string('is_active')->default('1'); });
        }
        if (!Schema::hasColumn('users','password_changed_at')) {
            Schema::table('users', function ($table) { $table->timestamp('password_changed_at')->nullable(); });
        }
        if (!Schema::hasTable('events')) {
            Schema::create('events', function ($table) {
                $table->id();
                $table->string('judul_event');
                $table->string('slug')->nullable()->unique();
                $table->string('lokasi')->nullable();
                $table->string('harga')->default('');
                $table->text('deskripsi')->nullable();
                $table->string('tanggal')->nullable();
                $table->date('tanggal_mulai')->nullable();
                $table->date('tanggal_selesai')->nullable();
                $table->string('banner')->nullable();
                $table->enum('is_active', ['1','0'])->default('1');
                $table->unsignedInteger('kuota')->nullable();
                $table->string('venue')->nullable();
                $table->string('visibility',20)->default('public');
                $table->dateTime('registrasi_dibuka')->nullable();
                $table->dateTime('registrasi_ditutup')->nullable();
                $table->decimal('harga_amount',12,2)->nullable();
                $table->timestamps();
            });
        } else {
            foreach (['slug' => fn($t) => $t->string('slug')->nullable(), 'harga' => fn($t) => $t->string('harga')->default(''), 'harga_amount' => fn($t) => $t->decimal('harga_amount',12,2)->nullable(), 'kuota' => fn($t) => $t->unsignedInteger('kuota')->nullable(), 'venue' => fn($t) => $t->string('venue')->nullable(), 'visibility' => fn($t) => $t->string('visibility',20)->default('public'), 'tanggal_mulai' => fn($t) => $t->date('tanggal_mulai')->nullable(), 'tanggal_selesai' => fn($t) => $t->date('tanggal_selesai')->nullable(), 'registrasi_dibuka' => fn($t) => $t->dateTime('registrasi_dibuka')->nullable(), 'registrasi_ditutup' => fn($t) => $t->dateTime('registrasi_ditutup')->nullable(), 'deskripsi' => fn($t) => $t->text('deskripsi')->nullable(), 'judul_event' => fn($t) => $t->string('judul_event')->nullable(), 'lokasi' => fn($t) => $t->string('lokasi')->nullable(), 'tanggal' => fn($t) => $t->string('tanggal')->nullable(), 'banner' => fn($t) => $t->text('banner')->nullable(), 'is_active' => fn($t) => $t->enum('is_active',['1','0'])->default('1')] as $col => $cb) {
                if (!Schema::hasColumn('events', $col)) Schema::table('events', fn($table) => $cb($table));
            }
        }
        // Rebuild payment/ticket tables from scratch to guarantee isolation
        // from any prior test class that may have left a different schema
        // (e.g. CheckInTest drops/recreates users/tickets/orders).
        foreach (['payment_logs','payment_proofs','tickets','ticket_logs'] as $t) {
            if (Schema::hasTable($t)) Schema::dropIfExists($t);
        }
        Schema::create('payment_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_payment');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['id_payment', 'created_at']);
        });
        Schema::create('payment_proofs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_payment');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
        });
        Schema::create('tickets', function ($table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nomor_ticket', 30)->unique();
            $table->unsignedBigInteger('id_order');
            $table->string('qr_payload');
            $table->string('status', 30)->default('issued');
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('id_order');
            $table->index('status');
        });
        if (!Schema::hasTable('ticket_logs')) {
            Schema::create('ticket_logs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('id_ticket');
                $table->string('old_status', 30)->nullable();
                $table->string('new_status', 30);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['id_ticket', 'created_at']);
            });
        }
    }

    private function truncate(array $tables): void
    {
        foreach ($tables as $t) {
            if (Schema::hasTable($t)) DB::table($t)->delete();
        }
    }

    private function makeUser(string $role): User
    {
        $u = User::factory()->create(['id_anggota'=> 'MZT'.str_pad((string) random_int(10000,99999),5,'0',STR_PAD_LEFT), 'is_active'=>'1', 'password_changed_at' => now()]);
        HakAksesRole::create(['id_users'=>$u->id,'nama_role'=>$role,'hak_akses'=>'access']);
        return $u;
    }

    private function makeEvent(): \App\Models\Event
    {
        return \App\Models\Event::create([
            'judul_event'=>'E '.uniqid(),'slug'=>'e-'.uniqid(),'lokasi'=>'L','harga'=>'100000','deskripsi'=>'d','tanggal'=>'01/01/2026 - 02/01/2026','tanggal_mulai'=>'2026-01-01','tanggal_selesai'=>'2026-01-02','is_active'=>'1',
        ]);
    }

    private function makeOrder(User $owner, $event): Order
    {
        return Order::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nomor_order' => 'MZT-'.date('Y').'-'.str_pad((string) random_int(1,9999),4,'0',STR_PAD_LEFT),
            'id_event' => $event->id,
            'id_anggota' => $owner->id_anggota,
            'event_name' => $event->judul_event,
            'event_price' => $event->harga,
            'event_start_at' => now(),
            'total_amount' => 100000,
            'status_registrasi' => 'registered',
            'payment_status' => 'pending',
        ]);
    }

    private function makePayment(Order $order, string $status, ?User $creator = null): Payment
    {
        return Payment::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nomor_payment' => 'PAY-'.uniqid(),
            'id_order' => $order->id,
            'method' => 'transfer',
            'amount' => 100000,
            'status' => $status,
            'created_by' => $creator?->id,
        ]);
    }

    public function test_guest401(): void
    {
        $this->getJson('/api/payments')->assertStatus(401);
    }

    public function test_alumni403(): void
    {
        Sanctum::actingAs($this->makeUser('anggota'));
        $this->getJson('/api/payments')->assertStatus(403);
    }

    public function test_dashboard403(): void { Sanctum::actingAs($this->makeUser('dashboard')); $this->getJson('/api/payments')->assertStatus(403); }
    public function test_event403(): void { Sanctum::actingAs($this->makeUser('event')); $this->getJson('/api/payments')->assertStatus(403); }
    public function test_prisensi403(): void { Sanctum::actingAs($this->makeUser('prisensi')); $this->getJson('/api/payments')->assertStatus(403); }

    public function test_finance200(): void { Sanctum::actingAs($this->makeUser('finance')); $this->getJson('/api/payments')->assertStatus(200); }
    public function test_ketua200(): void { Sanctum::actingAs($this->makeUser('ketua')); $this->getJson('/api/payments')->assertStatus(200); }
    public function test_admin200(): void { Sanctum::actingAs($this->makeUser('admin')); $this->getJson('/api/payments')->assertStatus(200); }

    public function test_default_status_is_waiting_verification(): void
    {
        $owner = $this->makeUser('anggota');
        $event = $this->makeEvent();
        $order = $this->makeOrder($owner, $event);
        $this->makePayment($order, PaymentStatus::WAITING_VERIFICATION->value);
        $this->makePayment($order, PaymentStatus::PAID->value);

        Sanctum::actingAs($this->makeUser('finance'));
        $res = $this->getJson('/api/payments')->assertStatus(200)->json('data');
        $statuses = collect($res['data'])->pluck('status')->unique()->values()->all();
        $this->assertEquals([PaymentStatus::WAITING_VERIFICATION->value], $statuses);
    }

    public function test_explicit_status_filter(): void
    {
        $owner = $this->makeUser('anggota'); $event = $this->makeEvent(); $order = $this->makeOrder($owner,$event);
        $this->makePayment($order, PaymentStatus::PAID->value);
        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson('/api/payments?status=paid')->assertStatus(200)->assertJsonPath('data.data.0.status','paid');
    }

    public function test_event_id_filter(): void
    {
        $owner=$this->makeUser('anggota'); $e1=$this->makeEvent(); $e2=$this->makeEvent();
        $o1=$this->makeOrder($owner,$e1); $o2=$this->makeOrder($owner,$e2);
        $this->makePayment($o1, PaymentStatus::WAITING_VERIFICATION->value);
        $this->makePayment($o2, PaymentStatus::WAITING_VERIFICATION->value);
        Sanctum::actingAs($this->makeUser('finance'));
        $res=$this->getJson("/api/payments?event_id={$e1->id}")->assertStatus(200)->json('data');
        foreach ($res['data'] as $p) { $this->assertEquals($e1->id, $p['order']['id_event']); }
    }

    public function test_date_filter(): void
    {
        $owner=$this->makeUser('anggota'); $event=$this->makeEvent(); $order=$this->makeOrder($owner,$event);
        $p=$this->makePayment($order, PaymentStatus::WAITING_VERIFICATION->value);
        $p->forceFill(['created_at'=>now()->subDays(5)])->save();
        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson('/api/payments?date_from='.now()->subDays(1)->toDateString())->assertStatus(200)
            ->assertJsonMissing(['nomor_payment'=>$p->nomor_payment]);
    }

    public function test_q_search(): void
    {
        $owner=$this->makeUser('anggota'); $event=$this->makeEvent(); $order=$this->makeOrder($owner,$event);
        $pay=$this->makePayment($order, PaymentStatus::WAITING_VERIFICATION->value);
        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson('/api/payments?q='.substr($pay->nomor_payment,0,6))->assertStatus(200)
            ->assertJsonFragment(['nomor_payment'=>$pay->nomor_payment]);
    }

    public function test_pagination_meta_and_bounded_per_page(): void
    {
        $owner=$this->makeUser('anggota'); $event=$this->makeEvent();
        for ($i=0;$i<3;$i++){ $o=$this->makeOrder($owner,$event); $this->makePayment($o, PaymentStatus::WAITING_VERIFICATION->value); }
        Sanctum::actingAs($this->makeUser('finance'));
        $this->getJson('/api/payments?per_page=2')->assertStatus(200)->assertJsonStructure(['data'=>['data','current_page','last_page','per_page','total']]);
        $this->getJson('/api/payments?per_page=100')->assertStatus(422);
    }

    public function test_no_nplus_one(): void
    {
        $owner=$this->makeUser('anggota'); $event=$this->makeEvent();
        for($i=0;$i<5;$i++){ $o=$this->makeOrder($owner,$event); $this->makePayment($o, PaymentStatus::WAITING_VERIFICATION->value); }
        Sanctum::actingAs($this->makeUser('finance'));
        DB::enableQueryLog();
        $this->getJson('/api/payments?per_page=5')->assertStatus(200);
        $c=count(DB::getQueryLog());
        // Queue should be bounded ~3 queries regardless of rows
        $this->assertLessThanOrEqual(7, $c);
    }

    public function test_unauthorized_verify403(): void
    {
        $owner=$this->makeUser('anggota'); $event=$this->makeEvent(); $order=$this->makeOrder($owner,$event);
        $pay=$this->makePayment($order, PaymentStatus::WAITING_VERIFICATION->value);
        Sanctum::actingAs($this->makeUser('anggota'));
        $this->putJson("/api/payments/{$pay->uuid}/verify", ['status'=>'paid'])->assertStatus(403);
    }

    public function test_duplicate_verify_idempotent(): void
    {
        $owner=$this->makeUser('anggota'); $event=$this->makeEvent(); $order=$this->makeOrder($owner,$event);
        $pay=$this->makePayment($order, PaymentStatus::WAITING_VERIFICATION->value);
        $verifier=$this->makeUser('finance'); Sanctum::actingAs($verifier);
        $this->putJson("/api/payments/{$pay->uuid}/verify", ['status'=>'paid'])->assertStatus(200)->assertJsonPath('data.changed',true);
        $this->putJson("/api/payments/{$pay->uuid}/verify", ['status'=>'paid'])->assertStatus(200)->assertJsonPath('data.changed',false);
    }
}
