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
            ],
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



