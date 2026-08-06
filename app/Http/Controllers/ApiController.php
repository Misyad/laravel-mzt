<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\DataUser;
use App\Models\Event;
use App\Models\Berita;
use App\Models\Carosel;
use App\Models\Info_pesantren;
use App\Models\Tentang_mzt;
use App\Models\Activitas_log;
use App\Models\Prisensi_kehadiran;
use App\Models\Transaksi_event;
use App\Models\HakAksesRole;
use App\Models\Tanggal_event;
use App\Models\TemplateIdCard;
use App\Models\Kontak;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    /**
     * AUTH ENDPOINTS
     */
    public function login(Request $request)
    {
        $request->validate([
            'id_anggota' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('id_anggota', $request->id_anggota)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'id_anggota' => ['ID Anggota atau password salah.'],
            ]);
        }

        if ($user->is_active != '1') {
            throw ValidationException::withMessages([
                'id_anggota' => ['Akun tidak aktif.'],
            ]);
        }

        // Audit login (only on successful authentication).
        $user->last_login = now();
        $user->increment('login_count');

        // Create token
        $token = $user->createToken('api-token')->plainTextToken;

        // Get user data
        $userData = DataUser::where('id_users', $user->id)->first();

        // Get roles
        $roles = HakAksesRole::where('id_users', $user->id)->pluck('nama_role')->toArray();

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'id_anggota' => $user->id_anggota,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'foto' => $userData ? $userData->foto : null,
                'must_change_password' => empty($user->password_changed_at),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logout berhasil']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $userData = DataUser::where('id_users', $user->id)->first();
        $roles = HakAksesRole::where('id_users', $user->id)->pluck('nama_role')->toArray();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'id_anggota' => $user->id_anggota,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'foto' => $userData ? $userData->foto : null,
                'data' => $userData,
                'must_change_password' => empty($user->password_changed_at),
            ],
        ]);
    }

    /**
     * ME ENDPOINT (Phase 1): primary identity endpoint for the frontend.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $roles = HakAksesRole::where('id_users', $user->id)->pluck('nama_role')->toArray();
        $userData = DataUser::where('id_users', $user->id)->first();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'id_anggota' => $user->id_anggota,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'foto' => $userData ? $userData->foto : null,
                'must_change_password' => empty($user->password_changed_at),
            ],
        ]);
    }

    /**
     * PHASE 1 — PROFILE READ
     * Read-only: nama, id_anggota (NIAM / Nomor Anggota), tahun_masuk,
     * tahun_keluar, status, barcode. Editable: foto, no_hp, email, alamat,
     * pekerjaan, tempat_lahir.
     */
    public function profileGet(Request $request)
    {
        $user = $request->user();
        $data = DataUser::where('id_users', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'id_anggota' => $user->id_anggota,
                'email' => $user->email,
                'no_hp' => $data ? $data->no_hp : null,
                'alamat' => $data ? $data->alamat : null,
                'pekerjaan' => $data ? $data->pekerjaan : null,
                'tempat_lahir' => $data ? $data->tempat_lahir : null,
                'niqobah' => $data ? $data->niqobah : null,
                'tahun_masuk' => $data ? $data->tahun_masuk : null,
                'tahun_keluar' => $data ? $data->tahun_keluar : null,
                'foto' => $data ? $data->foto : null,
                'status' => $user->is_active,
                'barcode' => $data ? $data->barcode : null,
            ],
        ]);
    }

    /**
     * PHASE 1 — PROFILE UPDATE (editable fields only)
     */
    public function profileUpdateJson(Request $request)
    {
        $request->validate([
            'no_hp' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'pekerjaan' => 'nullable|string|max:255',
            'tempat_lahir' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:5120',
        ]);

        $user = $request->user();
        $data = DataUser::where('id_users', $user->id)->first();

        $foto = $data ? $data->foto : null;
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto')->store('image/anggota', 'public');
        }

        if ($data) {
            $data->update([
                'no_hp' => $request->input('no_hp', $data->no_hp),
                'alamat' => $request->input('alamat', $data->alamat),
                'pekerjaan' => $request->input('pekerjaan', $data->pekerjaan),
                'tempat_lahir' => $request->input('tempat_lahir', $data->tempat_lahir),
                'foto' => $foto,
            ]);
        }

        if ($request->filled('email')) {
            $user->update(['email' => $request->email]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'name' => $user->name,
                'id_anggota' => $user->id_anggota,
                'email' => $user->email,
                'no_hp' => $data ? $data->no_hp : null,
                'alamat' => $data ? $data->alamat : null,
                'pekerjaan' => $data ? $data->pekerjaan : null,
                'tempat_lahir' => $data ? $data->tempat_lahir : null,
                'foto' => $foto,
                'status' => $user->is_active,
            ],
        ]);
    }

    /**
     * PHASE 1 — CHANGE PASSWORD
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama salah.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah',
        ]);
    }

    /**
     * PHASE 1 — ID CARD
     * QR encodes users.id_anggota (the scannable member identity). The legacy
     * data_users.barcode remains as the physical card image.
     */
    public function idCard(Request $request)
    {
        $user = $request->user();
        $data = DataUser::where('id_users', $user->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'id_anggota' => $user->id_anggota,
                'name' => $user->name,
                'foto' => $data ? $data->foto : null,
                'niqobah' => $data ? $data->niqobah : null,
                'status' => $user->is_active,
                'barcode' => $data ? $data->barcode : null,
            ],
        ]);
    }

    /**
     * PHASE 1 — GENERATE ACCOUNT (members/{id}/account)
     * Create a login account for a member that does not have one yet.
     * Idempotent: returns 409 when the account already exists.
     */
    public function generateAccount($id)
    {
        $member = DataUser::where('id_users', $id)->first();

        if (User::where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Akun sudah tersedia. Gunakan Reset Password apabila anggota lupa password.',
            ], 409);
        }

        $lastUser = User::orderBy('id', 'desc')->first();
        $newId = $lastUser ? (int) $lastUser->id_anggota + 1 : 1001;
        $tempPassword = Str::random(10);

        $user = new User();
        $user->id = (int) $id;
        $user->name = 'Anggota';
        $user->email = null;
        $user->id_anggota = (string) $newId;
        $user->password = Hash::make($tempPassword);
        $user->is_active = '1';
        $user->password_changed_at = null;
        $user->save();

        if ($member) {
            $member->update(['is_active' => '1']);
        }

        foreach (['anggota', 'profil'] as $role) {
            HakAksesRole::create([
                'id_users' => $user->id,
                'nama_role' => $role,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dibuat.',
            'password' => $tempPassword,
        ], 201);
    }

    /**
     * PHASE 1 — BULK GENERATE ACCOUNT
     * Only creates accounts for members that do not have a user yet.
     * Idempotent: safe to run repeatedly; never overwrites existing data.
     */
    public function bulkGenerate(Request $request)
    {
        $members = DataUser::whereDoesNotHave('user')->get();

        $created = 0;
        foreach ($members as $member) {
            if (User::where('id', $member->id_users)->exists()) {
                continue;
            }

            $lastUser = User::orderBy('id', 'desc')->first();
            $newId = $lastUser ? (int) $lastUser->id_anggota + 1 : 1001;

            $user = new User();
            $user->id = (int) $member->id_users;
            $user->name = 'Anggota';
            $user->email = null;
            $user->id_anggota = (string) $newId;
            $user->password = Hash::make(Str::random(10));
            $user->is_active = '1';
            $user->password_changed_at = null;
            $user->save();

            foreach (['anggota', 'profil'] as $role) {
                HakAksesRole::create([
                    'id_users' => $user->id,
                    'nama_role' => $role,
                ]);
            }

            $created++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'created' => $created,
                'skipped' => $members->count() - $created,
            ],
        ]);
    }

    /**
     * PHASE 1 — RESET PASSWORD (members/{id}/account)
     * Gives the member a fresh temporary password; forces a change on login.
     */
    public function resetAccount(Request $request, $id)
    {
        $user = User::where('id', $id)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        $tempPassword = Str::random(10);

        $user->update([
            'password' => Hash::make($tempPassword),
            'password_changed_at' => null,
            'is_active' => '1',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil di-reset.',
            'password' => $tempPassword,
        ]);
    }

    /**
     * PHASE 1 — ACTIVATE / DEACTIVATE ACCOUNT (members/{id}/account/status)
     */
    public function setAccountStatus(Request $request, $id)
    {
        $request->validate([
            'is_active' => 'required|string|in:1,0',
        ]);

        $user = User::where('id', $id)->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Akun tidak ditemukan.'], 404);
        }

        $user->update(['is_active' => $request->is_active]);

        DataUser::where('id_users', $id)->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => $request->is_active === '1' ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.',
        ]);
    }

    /**
     * DASHBOARD ENDPOINTS
     */
    public function dashboardStats()
    {
        $event = Event::where('is_active', '1')->count();
        $event_selesai = DB::table('event_status')
            ->where('is_active', '1')
            ->whereRaw("status COLLATE utf8mb4_unicode_ci = ?", ['Complate'])
            ->count();
        $event_mendatang = DB::table('event_status')
            ->where('is_active', '1')
            ->whereRaw("status COLLATE utf8mb4_unicode_ci = ?", ['Upcomming'])
            ->count();
        $total_anggota = DataUser::where('is_active', '1')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'event_selesai' => $event_selesai,
                'event_mendatang' => $event_mendatang,
                'total_anggota' => $total_anggota,
            ],
        ]);
    }

    public function dashboardCalendar()
    {
        $data = DB::table('event_status')->where('is_active', '1')->get();
        $data_array = [];

        foreach ($data as $val) {
            $regex = '/\s*-\s*/';
            $dates = preg_split($regex, $val->tanggal);
            $start_date = trim($dates[0]);
            $end_date = trim($dates[1]);

            $start_date = Carbon::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
            $end_date = Carbon::createFromFormat('d/m/Y', $end_date)->addDay()->format('Y-m-d');

            $warna = "";
            if ($val->status == "Ongoing") {
                $warna = "#3abaf4";
            } elseif ($val->status == "Complate") {
                $warna = "#47c363";
            } elseif ($val->status == "Upcomming") {
                $warna = "#ffa426";
            }

            $data_array[] = [
                'title' => $val->judul_event,
                'start' => $start_date,
                'end' => $end_date,
                'backgroundColor' => $warna,
                'borderColor' => $warna,
                'textColor' => '#fff',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data_array,
        ]);
    }

    public function dashboardEvents()
    {
        $events = DB::table('event_status')
            ->where('is_active', '1')
            ->orderByRaw("FIELD(status COLLATE utf8mb4_unicode_ci, 'Ongoing', 'Upcomming', 'Complate')")
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * MEMBERS ENDPOINTS
     */
    public function membersIndex()
    {
        $members = DataUser::with('user')->where('is_active', '1')->get();
        $data = $members->map(function ($m) {
            return [
                'id' => $m->id,
                'id_users' => $m->id_users,
                'id_anggota' => $m->user ? $m->user->id_anggota : '',
                'nama' => $m->user ? $m->user->name : '',
                'email' => $m->user ? $m->user->email : '',
                'no_hp' => $m->no_hp ?? '',
                'alamat' => $m->alamat ?? '',
                'niqobah' => $m->niqobah ?? '',
                'pekerjaan' => $m->pekerjaan ?? '',
                'foto' => $m->foto,
                'tahun_masuk' => $m->tahun_masuk,
                'tahun_keluar' => $m->tahun_keluar,
                'tempat_lahir' => $m->tempat_lahir ?? '',
                'tanggal_lahir' => $m->tanggal_lahir,
                // Phase 1 UX: expose existing users columns to let the member
                // grid surface account status. Additive only — no schema change.
                'has_account' => $m->user ? true : false,
                'account_is_active' => $m->user ? (int) $m->user->is_active : 0,
                'login_count' => $m->user ? (int) $m->user->login_count : 0,
                'last_login' => $m->user ? $m->user->last_login : null,
            ];
        });
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function membersShow($id)
    {
        $member = DataUser::with('user')->where('id_users', $id)->first();
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Member not found'], 404);
        }
        $data = [
            'id' => $member->id,
            'id_users' => $member->id_users,
            'id_anggota' => $member->user ? $member->user->id_anggota : '',
            'nama' => $member->user ? $member->user->name : '',
            'email' => $member->user ? $member->user->email : '',
            'no_hp' => $member->no_hp ?? '',
            'alamat' => $member->alamat ?? '',
            'niqobah' => $member->niqobah ?? '',
            'pekerjaan' => $member->pekerjaan ?? '',
            'foto' => $member->foto,
            'tahun_masuk' => $member->tahun_masuk,
            'tahun_keluar' => $member->tahun_keluar,
            'tempat_lahir' => $member->tempat_lahir ?? '',
            'tanggal_lahir' => $member->tanggal_lahir,
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function membersStore(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'niqobah' => 'required|string|max:15',
            'pekerjaan' => 'required|string|max:15',
            'tanggal_lahir' => 'required|date',
            'tahun_masuk' => 'required|date',
            'tahun_keluar' => 'required|date',
            'no_hp' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Generate ID Anggota
            $lastUser = User::orderBy('id', 'desc')->first();
            $newId = $lastUser ? $lastUser->id_anggota + 1 : 1001;

            // Generate barcode
            $barcode = 'MZT' . str_pad($newId, 5, '0', STR_PAD_LEFT);

            // Create user
            $user = User::create([
                'name' => $request->nama,
                'email' => $request->email ?? '',
                'id_anggota' => $newId,
                'password' => Hash::make($request->password),
                'is_active' => '1',
            ]);

            // Handle photo upload
            $foto = '';
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto')->store('image/anggota', 'public');
            }

            // Create data user
            $dataUser = DataUser::create([
                'id_users' => $user->id,
                'tempat_lahir' => $request->tempat_lahir ?? '',
                'alamat' => $request->alamat,
                'niqobah' => $request->niqobah,
                'pekerjaan' => $request->pekerjaan,
                'tanggal_lahir' => $request->tanggal_lahir,
                'tahun_masuk' => $request->tahun_masuk,
                'tahun_keluar' => $request->tahun_keluar,
                'no_hp' => $request->no_hp,
                'foto' => $foto,
                'barcode' => $barcode,
                'is_active' => '1',
            ]);

            // Handle roles
            if ($request->has('roles')) {
                foreach ($request->roles as $role) {
                    HakAksesRole::create([
                        'id_users' => $user->id,
                        'nama_role' => $role,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Member created successfully',
                'data' => $dataUser,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function membersUpdate(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'niqobah' => 'required|string|max:15',
            'pekerjaan' => 'required|string|max:15',
            'tanggal_lahir' => 'required|date',
            'tahun_masuk' => 'required|date',
            'tahun_keluar' => 'required|date',
            'no_hp' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $dataUser = DataUser::where('id_users', $id)->first();
            if (!$dataUser) {
                return response()->json(['success' => false, 'message' => 'Member not found'], 404);
            }

            $user = User::find($id);

            // Handle photo upload
            $foto = $dataUser->foto;
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto')->store('image/anggota', 'public');
            }

            // Update data user (data_users table)
            $dataUser->update([
                'alamat' => $request->alamat,
                'niqobah' => $request->niqobah,
                'pekerjaan' => $request->pekerjaan,
                'tanggal_lahir' => $request->tanggal_lahir,
                'tempat_lahir' => $request->tempat_lahir ?? '',
                'tahun_masuk' => $request->tahun_masuk,
                'tahun_keluar' => $request->tahun_keluar,
                'no_hp' => $request->no_hp,
                'foto' => $foto,
            ]);

            // Update user
            $user->update([
                'name' => $request->nama,
                'email' => $request->email ?? '',
            ]);

            // Update password if provided
            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            // Update roles
            if ($request->has('roles')) {
                HakAksesRole::where('id_users', $id)->delete();
                foreach ($request->roles as $role) {
                    HakAksesRole::create([
                        'id_users' => $id,
                        'nama_role' => $role,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully',
                'data' => $dataUser,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function membersDestroy($id)
    {
        DB::beginTransaction();
        try {
            $dataUser = DataUser::where('id_users', $id)->first();
            if (!$dataUser) {
                return response()->json(['success' => false, 'message' => 'Member not found'], 404);
            }

            $dataUser->update(['is_active' => '0']);
            User::where('id', $id)->update(['is_active' => '0']);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Member deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * EVENTS ENDPOINTS
     */
    public function eventsIndex()
    {
        $events = Event::where('is_active', '1')->get();
        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function eventsShow($id)
    {
        $event = Event::where('id', $id)->where('is_active', '1')->first();
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $event]);
    }

    public function eventsStore(Request $request)
    {
        $request->validate([
            'judul_event' => 'required|string|max:255',
            'slug' => 'required|string|unique:events,slug',
            'lokasi' => 'required|string',
            'harga' => 'required|numeric',
            'deskripsi' => 'required|string',
            'tanggal' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Handle banner upload
            $banner = '';
            if ($request->hasFile('banner')) {
                $banner = $request->file('banner')->store('image/event', 'public');
            }

            // Parse date
            $dates = explode(' - ', $request->tanggal);
            $tanggal_mulai = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
            $tanggal_selesai = isset($dates[1]) ? Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d') : $tanggal_mulai;

            $event = Event::create([
                'judul_event' => $request->judul_event,
                'slug' => $request->slug,
                'lokasi' => $request->lokasi,
                'harga' => $request->harga,
                'deskripsi' => $request->deskripsi,
                'banner' => $banner,
                'tanggal' => $request->tanggal,
                'tanggal_mulai' => $tanggal_mulai,
                'tanggal_selesai' => $tanggal_selesai,
                'is_active' => '1',
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function eventsUpdate(Request $request, $id)
    {
        $request->validate([
            'judul_event' => 'required|string|max:255',
            'slug' => 'required|string|unique:events,slug,' . $id,
            'lokasi' => 'required|string',
            'harga' => 'required|numeric',
            'deskripsi' => 'required|string',
            'tanggal' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $event = Event::where('id', $id)->where('is_active', '1')->first();
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }

            // Handle banner upload
            $banner = $event->banner;
            if ($request->hasFile('banner')) {
                $banner = $request->file('banner')->store('image/event', 'public');
            }

            // Parse date
            $dates = explode(' - ', $request->tanggal);
            $tanggal_mulai = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->format('Y-m-d');
            $tanggal_selesai = isset($dates[1]) ? Carbon::createFromFormat('d/m/Y', trim($dates[1]))->format('Y-m-d') : $tanggal_mulai;

            $event->update([
                'judul_event' => $request->judul_event,
                'slug' => $request->slug,
                'lokasi' => $request->lokasi,
                'harga' => $request->harga,
                'deskripsi' => $request->deskripsi,
                'banner' => $banner,
                'tanggal' => $request->tanggal,
                'tanggal_mulai' => $tanggal_mulai,
                'tanggal_selesai' => $tanggal_selesai,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function eventsDestroy($id)
    {
        $event = Event::where('id', $id)->where('is_active', '1')->first();
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        $event->update(['is_active' => '0']);
        return response()->json(['success' => true, 'message' => 'Event deleted successfully']);
    }

    /**
     * List the attendance dates of an event (Tanggal_event rows).
     */
    public function eventTanggal($id)
    {
        $tanggal = Tanggal_event::where('id_event', $id)->get();

        return response()->json([
            'success' => true,
            'data' => $tanggal,
        ]);
    }

    /**
     * NEWS ENDPOINTS
     */
    public function newsIndex()
    {
        $news = Berita::where('is_active', '1')->get();
        return response()->json([
            'success' => true,
            'data' => $news,
        ]);
    }

    public function newsShow($id)
    {
        $news = Berita::where('id', $id)->where('is_active', '1')->first();
        if (!$news) {
            return response()->json(['success' => false, 'message' => 'News not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $news]);
    }

    public function newsStore(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'slug' => 'required|string|unique:beritas,slug',
            'deskripsi' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $foto = '';
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto')->store('image/berita', 'public');
            }

            $news = Berita::create([
                'judul' => $request->judul,
                'slug' => $request->slug,
                'deskripsi' => $request->deskripsi,
                'foto' => $foto,
                'is_active' => '1',
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'News created successfully',
                'data' => $news,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function newsUpdate(Request $request, $id)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'slug' => 'required|string|unique:beritas,slug,' . $id,
            'deskripsi' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $news = Berita::where('id', $id)->where('is_active', '1')->first();
            if (!$news) {
                return response()->json(['success' => false, 'message' => 'News not found'], 404);
            }

            $foto = $news->foto;
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto')->store('image/berita', 'public');
            }

            $news->update([
                'judul' => $request->judul,
                'slug' => $request->slug,
                'deskripsi' => $request->deskripsi,
                'foto' => $foto,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'News updated successfully',
                'data' => $news,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function newsDestroy($id)
    {
        $news = Berita::where('id', $id)->where('is_active', '1')->first();
        if (!$news) {
            return response()->json(['success' => false, 'message' => 'News not found'], 404);
        }

        $news->update(['is_active' => '0']);
        return response()->json(['success' => true, 'message' => 'News deleted successfully']);
    }

    /**
     * ATTENDANCE ENDPOINTS
     */
    public function attendanceIndex($eventId, $tanggalId)
    {
        $attendance = Prisensi_kehadiran::where('id_event', $eventId)
            ->where('id_tanggal', $tanggalId)
            ->with('dataUser:id,id_anggota,name')
            ->get()
            ->map(function ($row) {
                $data = $row->toArray();
                unset($data['dataUser']);
                $data['dataUser'] = $row->dataUser ? ['id' => $row->dataUser->id, 'nama' => $row->dataUser->name] : null;
                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $attendance,
        ]);
    }

    public function attendanceStore(Request $request)
    {
        $request->validate([
            'id_anggota' => 'required|string',
            'id_event' => 'required|integer',
            'id_tanggal' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $user = User::where('id_anggota', $request->id_anggota)->first();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Member not found'], 404);
            }

            $dataUser = DataUser::where('id_users', $user->id)->first();
            if (!$dataUser) {
                return response()->json(['success' => false, 'message' => 'Member data not found'], 404);
            }

            // Check if already present
            $existing = Prisensi_kehadiran::where('id_anggota', $user->id_anggota)
                ->where('id_event', $request->id_event)
                ->where('id_tanggal', $request->id_tanggal)
                ->whereDate('tanggal_kehadiran', Carbon::today())
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member already present today',
                ], 400);
            }

            $attendance = Prisensi_kehadiran::create([
                'id_anggota' => $user->id_anggota,
                'id_event' => $request->id_event,
                'id_tanggal' => $request->id_tanggal,
                'tanggal_kehadiran' => Carbon::today(),
                'jam_kehadiran' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Attendance recorded successfully',
                'data' => $attendance,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * TRANSACTIONS ENDPOINTS
     */
    public function transactionsIndex($eventId)
    {
        $transactions = Transaksi_event::where('id_event', $eventId)
            ->with('dataUser:id,id_anggota,name')
            ->get()
            ->map(function ($row) {
                $data = $row->toArray();
                unset($data['dataUser']);
                $data['dataUser'] = $row->dataUser ? ['id' => $row->dataUser->id, 'nama' => $row->dataUser->name] : null;
                return $data;
            });

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * CONTENT ENDPOINTS
     */
    public function carouselIndex()
    {
        $carousel = Carosel::orderBy('id')->get();
        return response()->json([
            'success' => true,
            'data' => $carousel,
        ]);
    }

    public function carouselUpdate(Request $request, $id)
    {
        $carousel = Carosel::where('id', $id)->first();
        if (!$carousel) {
            return response()->json(['success' => false, 'message' => 'Carousel not found'], 404);
        }

        if ($request->hasFile('foto')) {
            $foto = $request->file('foto')->store('image/carousel', 'public');
            $carousel->update(['foto' => $foto]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carousel updated successfully',
            'data' => $carousel,
        ]);
    }

    public function infoPesantren()
    {
        $info = Info_pesantren::first();
        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    public function infoPesantrenUpdate(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'alamat' => 'required|string',
            'telpon' => 'required|string',
        ]);

        $info = Info_pesantren::first();
        if (!$info) {
            return response()->json(['success' => false, 'message' => 'Info not found'], 404);
        }

        $foto = $info->foto;
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto')->store('image/pesantren', 'public');
        }

        $info->update([
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'alamat' => $request->alamat,
            'telpon' => $request->telpon,
            'email' => $request->email,
            'foto' => $foto,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Info updated successfully',
            'data' => $info,
        ]);
    }

    public function infoMzt()
    {
        $info = Tentang_mzt::first();
        return response()->json([
            'success' => true,
            'data' => $info,
        ]);
    }

    public function infoMztUpdate(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'alamat' => 'required|string',
            'telpon' => 'required|string',
        ]);

        $info = Tentang_mzt::first();
        if (!$info) {
            return response()->json(['success' => false, 'message' => 'Info not found'], 404);
        }

        $foto = $info->foto;
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto')->store('image/mzt', 'public');
        }

        $info->update([
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'alamat' => $request->alamat,
            'telpon' => $request->telpon,
            'email' => $request->email,
            'foto' => $foto,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Info updated successfully',
            'data' => $info,
        ]);
    }


    /**
     * KTA CARD (HTML view)
     */
    public function ktaView($id)
    {
        $dataUser = DataUser::where('id_users', $id)->first();
        $user = User::where('id', $id)->first();

        if (!$dataUser || !$user) {
            abort(404, 'Member not found');
        }

        $fotoPath = $dataUser->foto ? '/storage/' . $dataUser->foto : '/assets/avatar-1.png';
        $cekFoto = $dataUser->foto ? public_path('storage/' . $dataUser->foto) : '';
        if (empty($dataUser->foto) || !\Illuminate\Support\Facades\File::exists($cekFoto)) {
            $fotoPath = '/assets/avatar-1.png';
        }

        $data = [
            'nama' => $user->name,
            'id_anggota' => $user->id_anggota,
            'alamat' => $dataUser->alamat,
            'niqobah' => $dataUser->niqobah,
            'tahun_masuk' => date('Y', strtotime($dataUser->tahun_masuk)),
            'tahun_keluar' => date('Y', strtotime($dataUser->tahun_keluar)),
            'bracode' => $dataUser->barcode,
            'profil' => $fotoPath,
        ];

        return view('kta', $data);
    }

    /**
     * ACTIVITY LOG ENDPOINTS
     */
    public function activityLogIndex()
    {
        $logs = Activitas_log::with('dataUser:id,name')
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'id_users' => $log->user_id,
                    'aktivitas' => $log->subject,
                    'url' => $log->url,
                    'method' => $log->method,
                    'agent' => $log->agent,
                    'created_at' => $log->created_at,
                    'dataUser' => $log->dataUser ? ['id' => $log->dataUser->id, 'nama' => $log->dataUser->name] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function activityLogUser($userId)
    {
        $logs = Activitas_log::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'id_users' => $log->user_id,
                    'aktivitas' => $log->subject,
                    'url' => $log->url,
                    'method' => $log->method,
                    'agent' => $log->agent,
                    'created_at' => $log->created_at,
                    'dataUser' => $log->dataUser ? ['id' => $log->dataUser->id, 'nama' => $log->dataUser->name] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * PROFILE ENDPOINTS
     */
    public function profileUpdate(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'niqobah' => 'required|string|max:15',
            'pekerjaan' => 'required|string|max:15',
            'tanggal_lahir' => 'required|date',
            'tahun_masuk' => 'required|date',
            'tahun_keluar' => 'required|date',
            'no_hp' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $user = $request->user();
            $dataUser = DataUser::where('id_users', $user->id)->first();

            $foto = $dataUser ? $dataUser->foto : '';
            if ($request->hasFile('foto')) {
                $foto = $request->file('foto')->store('image/anggota', 'public');
            }

            if ($dataUser) {
                $dataUser->update([
                    'nama' => $request->nama,
                    'email' => $request->email ?? '',
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'pekerjaan' => $request->pekerjaan,
                    'tanggal_lahir' => $request->tanggal_lahir,
                    'tahun_masuk' => $request->tahun_masuk,
                    'tahun_keluar' => $request->tahun_keluar,
                    'no_hp' => $request->no_hp,
                    'foto' => $foto,
                ]);
            }

            $user->update([
                'name' => $request->nama,
                'email' => $request->email ?? '',
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $dataUser,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUBLIC ENDPOINTS
     */

    /**
     * Public statistics for the landing page (mirrors dashboardStats).
     */
    public function publicStats()
    {
        $event = Event::where('is_active', '1')->count();
        $event_selesai = DB::table('event_status')
            ->where('is_active', '1')
            ->whereRaw("status COLLATE utf8mb4_unicode_ci = ?", ['Complate'])
            ->count();
        $event_mendatang = DB::table('event_status')
            ->where('is_active', '1')
            ->whereRaw("status COLLATE utf8mb4_unicode_ci = ?", ['Upcomming'])
            ->count();
        $total_anggota = DataUser::where('is_active', '1')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'event_selesai' => $event_selesai,
                'event_mendatang' => $event_mendatang,
                'total_anggota' => $total_anggota,
            ],
        ]);
    }

    /**
     * Store a contact message from the public contact form.
     */
    public function contactStore(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'pesan' => 'required|string',
        ]);

        $kontak = Kontak::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'pesan' => $request->pesan,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim',
            'data' => $kontak,
        ], 201);
    }
}



