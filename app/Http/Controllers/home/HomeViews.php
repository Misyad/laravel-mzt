<?php

namespace App\Http\Controllers\home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Info_pesantren;
use App\Models\Carosel;
use App\Models\Tentang_mzt;
use App\Models\Berita;
use App\Models\Event;
use App\Models\Event_status;
use App\Models\Tanggal_event;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\DataUser;
use App\Models\HakAksesRole;
use DNS1D;
use Illuminate\Support\Facades\Storage;
use Image;
use Illuminate\Support\Facades\Hash;
use App\Models\Transaksi_event;
use DataPicker;
use Illuminate\Support\Facades\File;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;

class HomeViews extends Controller
{
    function index()
    {
        $info_pesantren = Info_pesantren::where('id', '1')->first();
        $carosel = Carosel::where('id', '1')->first();
        $tentang_mzt = Tentang_mzt::where('id', '1')->first();
        $berita = Berita::join('users', 'users.id_anggota', '=', 'beritas.create_at')
            ->where('beritas.is_active', '1')
            ->select('users.name as nama', 'beritas.*')
            ->orderBy('beritas.created_at', 'desc')
            ->LIMIT(6)
            ->get();

        return view('home.home_views', ['info_pesantren' => $info_pesantren, 'carosel' => $carosel, 'tentang_mzt' => $tentang_mzt, 'berita' => $berita]);
    }

    function tentagMzt()
    {
        $tentang_mzt = Tentang_mzt::where('id', '1')->first();
        return view('home.tentang_mzt', ['tentang_mzt' => $tentang_mzt]);
    }

    function bertiaDetail(Request $request, $id)
    {
        $count = Berita::where('slug', $id)->count();

        if ($count == 0) {
            return abort(404);
        }
        $berita = Berita::join('users', 'users.id_anggota', '=', 'beritas.create_at')
            ->where(['beritas.is_active' => '1', 'slug' => $id])
            ->select('users.name as nama', 'beritas.*')
            ->first();
        $tentang_mzt = Tentang_mzt::where('id', '1')->first();

        return view('home.berita_detail', ['berita' => $berita,  'tentang_mzt' => $tentang_mzt]);
    }

    function berita()
    {
        $count = Berita::join('users', 'users.id_anggota', '=', 'beritas.create_at')
            ->where('beritas.is_active', '1')
            ->select('users.name as nama', 'beritas.*')
            ->orderBy('beritas.created_at', 'desc')
            ->count();

        $berita = Berita::join('users', 'users.id_anggota', '=', 'beritas.create_at')
            ->where('beritas.is_active', '1')
            ->select('users.name as nama', 'beritas.*')
            ->orderBy('beritas.created_at', 'desc')
            ->paginate(10);
        $tentang_mzt = Tentang_mzt::where('id', '1')->first();

        return view('home.berita', ['berita' => $berita,  'tentang_mzt' => $tentang_mzt, 'count' => $count]);
    }

    function event()
    {
        $count = Event_status::where('is_active', '1')->orderBy(DB::raw("FIELD(status COLLATE utf8mb4_unicode_ci, 'Ongoing', 'Upcomming', 'Complate')"))
            ->count();

        $event = Event_status::where('is_active', '1')->orderBy(DB::raw("FIELD(status COLLATE utf8mb4_unicode_ci, 'Ongoing', 'Upcomming', 'Complate')"))
            ->paginate(10);
        $tentang_mzt = Tentang_mzt::where('id', '1')->first();

        return view('home.pengumunan', ['event' => $event,  'tentang_mzt' => $tentang_mzt, 'count' => $count]);
    }

    function eventDetail(Request $request, $id)
    {
        $count = Event_status::where('slug', $id);
        if ($count->count() == 0) {
            return abort(404);
        }

        $event = Event_status::where('slug', $id)->first();
        $tangga_event = Tanggal_event::where('id_event', $event->id)->get();
        $tentang_mzt = Tentang_mzt::where('id', '1')->first();
        $tanggalMulai = Carbon::parse($event->tanggal_mulai); // Ganti dengan tanggal mulai yang sesuai
        $tanggalSelesai = Carbon::parse($event->tanggal_selesai); // Ganti dengan tanggal selesai yang sesuai
        $tanggalSekarang = Carbon::now();
        if ($tanggalSekarang  <= $tanggalSelesai) {
            $status_tanggal = true;
        } else {
            $status_tanggal = false;
        }

        $harga_grend = $event->harga;
        $hargaTotal = (int)str_replace(array("Rp. ", "."), "", $harga_grend);


        return view('home.pengumunan_detail', [
            'harga' => $hargaTotal , 'event' => $event,  'tentang_mzt' => $tentang_mzt,
            'tanggal_event' => $tangga_event, 'status_tanggal' => $status_tanggal,
            'id_event' => $count->first()->id , 'default_harga' => $event->harga
        ]);
    }

    function pembayaranInfak(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'g-recaptcha-response' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => true,
                'message' => 'Gagal!!',
                'data'    => 'pastikan anda telah mengisi recaptcha'
            ], 500);
        }

        $tentang_mzt = Tentang_mzt::where('id', '1')->first();
        $harga_grend = $request->infak;
        $hargaTotal = (int)str_replace(array("Rp. ", "."), "", $harga_grend);
        date_default_timezone_set('Asia/Jakarta');

        $request->validate([
            'nama' => ['required'],
            'alamat' => ['required'],
            'tanggal_lahir' => ['required'],
            'tahun_masuk' => ['required'],
            'tahun_keluar' => ['required'],
            'niqobah' => ['required'],
            'tempat_lahir' => ['required'],
            'nomer_telpon' => ['required'],
            'foto' => ['image', 'mimes:jpg,png,jpeg,gif,svg', 'max:1048'],
        ]);

        $jml = User::count();
        $jml2 = $jml + 1;
        $paddedNumber = str_pad($jml2, 4, "0", STR_PAD_LEFT);
        $tahun = substr(date("Y", strtotime($request->tanggal_lahir)), -2);
        $tahun_masuk = substr(date("Y", strtotime($request->tahun_masuk)), -2);
        $tahun_keluar = substr(date("Y", strtotime($request->tahun_keluar)), -2);
        $id_anggota = $paddedNumber . $tahun . $tahun_masuk . $tahun_keluar;

        // ini untuk id transaksi
        $timestamp = now()->format('ymd'); // Mengambil tanggal dalam format YYMMDD
        $randomNumber = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT); // Menghasilkan angka acak 4 digit dengan leading zero jika diperlukan
        $id_transaksi = $timestamp . $randomNumber;

        $data_tlp = DataUser::where(['no_hp' => $request->nomer_telpon])->count();
        $file_status = $_FILES["foto"]["name"];

        if (($data_tlp == 0)) {

            $barcode = \DNS1D::getBarcodePNG($id_anggota, 'C39');
            $filename = 'barcode-' . $id_anggota . '.png';
            $image_path_barcode = 'image/barcode/' . $filename;
            Storage::disk('public')->put('image/barcode/' . $filename, base64_decode($barcode));
            $id = User::insertGetId([
                'id_anggota' => $id_anggota,
                'name' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($id_anggota),
            ]);
            if ($file_status) {

                $image_path = $request->file('foto')->store('image/anggota', 'public');
                $image = Image::make(storage_path('app/public/' . $image_path));
                $image->resize(300, 400); // Mengubah ukuran gambar
                $image->save();

                $status = DataUser::insert([
                    'id_users' => $id,
                    'barcode' => $image_path_barcode,
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->nomer_telpon,
                    'pekerjaan' => $request->pekerjaan,
                    'tempat_lahir' => $request->tempat_lahir,
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->tanggal_lahir)),
                    'tahun_masuk' => date("Y-m-d", strtotime($request->tahun_masuk)),
                    'tahun_keluar' => date("Y-m-d", strtotime($request->tahun_keluar)),
                    'foto' => $image_path,
                ]);
            } else {
                    DataUser::insert([
                    'id_users' => $id,
                    'barcode' => $image_path_barcode,
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->nomer_telpon,
                    'pekerjaan' => $request->pekerjaan,
                    'tempat_lahir' => $request->tempat_lahir,
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->tanggal_lahir)),
                    'tahun_masuk' => date("Y-m-d", strtotime($request->tahun_masuk)),
                    'tahun_keluar' => date("Y-m-d", strtotime($request->tahun_keluar)),
                ]);
            }
            if(!($request->infak == null)){
                $snapToken = $this->midtransShow($request, $hargaTotal,$request->id_event,);
                   Transaksi_event::insert([
                    'id_event'          => $request->id_event,
                    'id_anggota'        => $id_anggota,
                    'snaptoken'         => $snapToken,
                    'order_id'          => $id_transaksi,
                    'created_at'        => date("Y-m-d H:i:s"),
                    'updated_at'        => date("Y-m-d H:i:s"),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'pendaftaran  berhasil!!',
                    'data'    => [
                        'snap' => $snapToken,
                        'id_anggota' => $id_anggota,
                    ]
                ], 200);
            }else{
                $message = "🔵 Pembayaran Anda Pending!\n\n"
                . "Segera lakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                . "➡ Id Anggota: $id_anggota\n"
                . "➡ Nama : $request->nama\n"
                . "➡ Id Transaksi: $id_transaksi\n"
                . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                . "Salam,\n"
                . "Maziltu Tholiban";

                DataPicker::sendWa($request->nomer_telpon, $message);

                 Transaksi_event::insert([
                    'id_event'          => $request->id_event,
                    'id_anggota'        => $id_anggota,
                    'order_id'          => $id_transaksi,
                    'transaction_status'=> 'offline',
                    'created_at'        => date("Y-m-d H:i:s"),
                    'updated_at'        => date("Y-m-d H:i:s"),
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'pendaftaran  berhasil!!',
                    'body'    => 'Segera lakukan pembayaran di admin/panitia',
                    'data'    => 'null'
                ], 200);
            }


        } else {


            $data_user = DataUser::join('users', 'users.id', '=', 'data_users.id_users')
                ->select('data_users.*', 'users.name', 'users.id_anggota', 'users.id as id_users')
                ->where('data_users.no_hp', $request->nomer_telpon)->first();
            $statusPendaftaran = Transaksi_event::where(['id_anggota' => $data_user->id_anggota, 'id_event' => $request->id_event]);

            $status = User::where('id', $data_user->id_users)->update([
                'name' => $request->nama,
                'email' => $request->email,
                'is_active' => '1',
            ]);

            if($file_status){
                $filePath = 'storage/' . $data_user->foto;
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }

                $image_path = $request->file('foto')->store('image/anggota', 'public');
                $status = DataUser::where('id_users', $data_user->id_users)->update([
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->nomer_telpon,
                    'pekerjaan' => $request->pekerjaan,
                    'tempat_lahir' => $request->tempat_lahir,
                    'foto' => $image_path,
                    'is_active' => '1',
                ]);
            }else{
                $status = DataUser::where('id_users', $data_user->id_users)->update([
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->nomer_telpon,
                    'pekerjaan' => $request->pekerjaan,
                    'tempat_lahir' => $request->tempat_lahir,
                    'is_active' => '1',
                ]);
            }




            if ($statusPendaftaran->count() != 0) {
                if ($statusPendaftaran->first()->transaction_status == 'settlement') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal!!',
                        'data'    => 'anda sudah terdaftar dan pembayaran anda sudah berhasil'
                    ], 200);
                }

                if(!($request->infak == null)){
                    $snapToken = $this->midtransShow($request, $hargaTotal, $request->id_event);
                    Transaksi_event::where(['id_anggota' => $data_user->id_anggota, 'id_event' => $request->id_event])
                        ->update(['snaptoken' => $snapToken]);

                    // $snapToken = $statusPendaftaran->first()->snaptoken;
                    // if(empty($statusPendaftaran->first()->snaptoken)){
                    // }

                    return response()->json([
                        'success' => true,
                        'message' => 'anda sudah terdaftar pada event ini!!',
                        'data'    => [
                            'snap' => $snapToken,
                            'id_anggota' => $data_user->id_anggota,
                        ]
                    ], 200);

                   }else{
                    Transaksi_event::where(['id_anggota' => $data_user->id_anggota, 'id_event' => $request->id_event])
                    ->update([
                        'id_event' => $request->id_event,
                        'id_anggota' => $data_user->id_anggota,
                        'transaction_status'=> 'offline',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'pendaftaran  berhasil!!',
                        'body'    => 'Segera lakukan pembayaran di admin/panitia',
                        'data'    => 'null'
                    ], 200);
                   }

                }else{
                    $message = "🔵 Pembayaran Anda telah diterima!\n\n"
                    . "Terima kasih telah melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $data_user->id_anggota\n"
                    . "➡ Nama : $request->nama\n"
                    . "➡ Id Transaksi: $id_transaksi\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($request->nomer_telpon, $message);

                if(!($request->infak == null)){
                    $snapToken = $this->midtransShow($request, $hargaTotal, $request->id_event);
                    Transaksi_event::insert([
                    'id_event'          => $request->id_event,
                    'id_anggota'        => $data_user->id_anggota,
                    'order_id'          => $id_transaksi,
                    'snaptoken'         => $snapToken,
                    'created_at'        => date("Y-m-d H:i:s"),
                    'updated_at'        => date("Y-m-d H:i:s"),
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'pendaftaran  berhasil!!',
                    'data'    => [
                        'snap' => $snapToken,
                        'id_anggota' => $data_user->id_anggota,
                    ]
                ], 200);
                }else{
                    Transaksi_event::insert([
                        'id_event' => $request->id_event,
                        'id_anggota' => $data_user->id_anggota,
                        'order_id' => $id_transaksi,
                        'transaction_status'=> 'offline',
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'pendaftaran  berhasil!!',
                        'body'    => 'Segera lakukan pembayaran di admin/panitia',
                        'data'    => 'null'
                    ], 200);
                }
            }
        }
    }

    function midtransShow($request, $harga_grend, $id_event)
    {

        //   Set your Merchant Server Key
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
        \Midtrans\Config::$isProduction = false;
        // Set sanitization on (default)
        \Midtrans\Config::$isSanitized = true;
        // Set 3DS transaction for credit card to true
        \Midtrans\Config::$is3ds = true;

        $params = array(
            'transaction_details' => array(
                'order_id' => rand(),
                'gross_amount' => $harga_grend+5000,
            ),
            'customer_details' => array(
                'first_name' => $request->nama,
                'phone' => $request->nomer_telpon,
            ),
            'item_details' => array(
                [
                    'id' => $id_event,
                    'price' => $harga_grend,
                    'quantity' => '1',
                    'name' => 'infak',
                ],
                [
                    'id' => "b02",
                    'price' => 5000,
                    'quantity' => '1',
                    'name' => 'Biaya Transaksi',
                ]
            ),


        );

        return  \Midtrans\Snap::getSnapToken($params);
    }

    function simpanPembayaran(Request $request)
    {
        $result = $request->result;
        $id_anggota = $request->id_anggota;
        $snaptoken = $request->snaptoken;
        date_default_timezone_set('Asia/Jakarta');

        $dataUser = DataUser::join('users', 'users.id', '=', 'data_users.id_users')
            ->join('m_transaksi_events', 'users.id_anggota', '=', 'm_transaksi_events.id_anggota')
            ->select('data_users.no_hp', 'users.name','m_transaksi_events.*')
            ->where('users.id_anggota', $id_anggota)->first();

        $data = [
            'payment_code' => isset($result['payment_code']) ? $result['payment_code'] : null,
            'payment_type' => isset($result['payment_type']) ? $result['payment_type'] : null,
            'pdf_url' => isset($result['pdf_url']) ? $result['pdf_url'] : null,
            'status_code' => isset($result['status_code']) ? $result['status_code'] : null,
            'status_message' => isset($result['status_message']) ? $result['status_message'] : null,
            'transaction_id' => isset($result['transaction_id']) ? $result['transaction_id'] : null,
            'transaction_status' => isset($result['transaction_status']) ? $result['transaction_status'] : null,
            'transaction_time' => isset($result['transaction_time']) ? $result['transaction_time'] : null,
            'updated_at' => date("Y-m-d H:i:s"),
        ];
        $time =   isset($result['transaction_time']) ? $result['transaction_time'] : '';
        $gross_amount =   isset($result['gross_amount']) ? $result['gross_amount'] : '';
        $order_id =   isset($result['order_id']) ? $result['order_id'] : '';
        $payment_code =   isset($result['payment_code']) ? $result['payment_code'] : '';


        if (in_array($result['status_code'], ['200', '201', '203'])) {
            if ($result['payment_type'] == 'qris') {
                if ($result['transaction_status'] == 'settlement') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['gross_amount'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Anda telah diterima!\n\n"
                    . "Terima kasih telah melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Id Transaksi: $order_id\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);

                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran  berhasil!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);
                } elseif ($result['transaction_status'] == 'pending') {
                    if (isset($result['order_id'])) {
                      $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['order_id'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }

                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);

                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran pending!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);
                } elseif ($result['transaction_status'] == 'expire') {

                    // DataPicker::sendWa($dataUser->no_hp, $pesan);
                    $response =  response()->json([
                        'success' => false,
                        'message' => 'Pembayaran gagal!!',
                        'data'    => 'coba lakukan pembayaran sekali lagi'
                    ], 500);
                }
            } elseif ($result['payment_type'] == 'cstore') {
                if ($result['transaction_status'] == 'settlement') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['gross_amount'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }
                    if (isset($result['payment_code'])) {
                        $data['payment_code'] = $result['payment_code'];
                    }


                    $message = "🔵 Pembayaran Anda Berhasil!\n\n"
                    . "Terima kasih telah melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Id Transaksi: $order_id\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);
                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran  berhasil!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);
                } elseif ($result['transaction_status'] == 'pending') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['gross_amount'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }
                    if (isset($result['payment_code'])) {
                        $data['payment_code'] = $result['payment_code'];
                    }


                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "➡ Kode vritual anda adalah: $payment_code\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);
                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran pending!!',
                        'data'    => 'segera lakukan pembayaran'
                    ], 200);
                } elseif ($result['transaction_status'] == 'expire') {


                    $message = "🔵 Pembayaran Anda Gagal!\n\n"
                    . "Lakuka pembayaran ulang:\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);
                    $response =  response()->json([
                        'success' => false,
                        'message' => 'Pembayaran gagal!!',
                        'data'    => 'coba lakukan pembayaran sekali lagi'
                    ], 500);
                }
            } elseif ($result['payment_type'] == 'bank_transfer') {
                if ($result['transaction_status'] == 'settlement') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['order_id'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Berhasil Berhasil!\n\n"
                    . "Terima kasih telah melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Id Transaksi: $order_id\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);
                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran  berhasil!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);

                } elseif ($result['transaction_status'] == 'pending') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['order_id'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera lakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);

                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran pending!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);
                } elseif ($result['transaction_status'] == 'expire') {
                    $response =  response()->json([
                        'success' => false,
                        'message' => 'Pembayaran gagal!!',
                        'data'    => 'coba lakukan pembayaran sekali lagi'
                    ], 500);
                }
            } elseif ($result['payment_type'] == 'credit_card') {
                if ($result['transaction_status'] == 'settlement') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['gross_amount'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera lakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota : $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Id Transaksi: $order_id\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);
                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran  berhasil!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);

                } elseif ($result['transaction_status'] == 'pending') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['order_id'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera lakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota : $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Id Transaksi: $order_id\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);

                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran pending!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);
                } elseif ($result['transaction_status'] == 'capture') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['gross_amount'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran pending!!',
                        'data'    => 'pembayaran di proses dalam 24 jam anda akan mendapatkan wa'
                    ], 200);
                }elseif ($result['transaction_status'] == 'expire') {
                    $response =  response()->json([
                        'success' => false,
                        'message' => 'Pembayaran gagal!!',
                        'data'    => 'coba lakukan pembayaran sekali lagi'
                    ], 500);
                }
            } else {

                if ($result['transaction_status'] == 'settlement') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['order_id'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera lakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Id Transaksi: $order_id\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);
                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran  berhasil!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);

                } elseif ($result['transaction_status'] == 'pending') {
                    if (isset($result['order_id'])) {
                        $data['order_id'] = $result['order_id'];
                    }
                    if (isset($result['order_id'])) {
                        $data['gross_amount'] = $result['gross_amount'];
                    }


                    $message = "🔵 Pembayaran Anda pending!\n\n"
                    . "Segera melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
                    . "➡ Id Anggota: $dataUser->id_anggota\n"
                    . "➡ Nama : $dataUser->name\n"
                    . "➡ Tanggal Pembayaran: $time\n"
                    . "➡ Jumlah Pembayaran: Rp. $gross_amount\n\n"
                    . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
                    . "Salam,\n"
                    . "Maziltu Tholiban";

                    DataPicker::sendWa($dataUser->no_hp, $message);

                    $response =  response()->json([
                        'success' => true,
                        'message' => 'Pembayaran pending!!',
                        'data'    => 'anda akan segera mendapatkan wa bila dalam 24 jam tidak ada wa hubungi admin'
                    ], 200);
                } elseif ($result['transaction_status'] == 'expire') {
                    $response =  response()->json([
                        'success' => false,
                        'message' => 'Pembayaran gagal!!',
                        'data'    => 'coba lakukan pembayaran sekali lagi'
                    ], 500);
                }else{
                    $response =  response()->json([
                        'success' => false,
                        'message' => 'Pembayaran gagal!!',
                        'data'    => 'coba lakukan pembayaran sekali lagi'
                    ], 500);
                }
            }

            $status = Transaksi_event::where('id_anggota', $id_anggota)->update($data);

            if ($status) {
                return $response;
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran  gagal!!',
                    'data'    => 'priksa form pendaftaran bila kesulitan hubungi admin'
                ], 500);
            }
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Pembayaran  gagal!!',
                'data'    => 'priksa form pendaftaran bila kesulitan hubungi admin'
            ], 500);
        }
    }
}
