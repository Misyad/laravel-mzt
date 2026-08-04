<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaksi_event;
use App\Models\Event;
use DataPicker;
use App\Models\DataUser;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Image;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use DNS1D;
use Illuminate\Support\Facades\DB;



class C_transaksi extends Controller
{
    function index()
    {
        return view('admin.event_transaksi');
    }

    function tabelTransaksi(Request $request)
    {
        $count = Event::where('id', $request->id)->count();

        if($count == 0){
            return abort(404);
        }
        $data['id_event'] = $request->id;
        $data['data'] = Event::where('id',$request->id)->first();
        $data['transaksi'] = Transaksi_event::join('users as s','s.id_anggota','=','m_transaksi_events.id_anggota')
                            ->join('data_users as du','s.id','=','du.id_users' )
                            ->select('s.name','s.id_anggota','m_transaksi_events.*','s.id as id_users')
                            ->where('id_event' , $request->id)->get();


        $data['data_user'] = DataUser::join('users', 'users.id', '=', 'data_users.id_users')
        ->leftJoin('m_transaksi_events as mte', 'mte.id_anggota', '=', 'users.id_anggota')
        ->select('data_users.*', 'users.name', 'users.id_anggota', 'users.id as id_users')
        ->whereNull('mte.id_anggota')
        ->get();


        return view('admin.tabel_transaksi', $data);
    }

    function payment_hendler(Request $request)
    {
        $json = json_decode($request->getContent());

        $signature_key = hash('sha512',$json->order_id . $json->status_code . $json->gross_amount . env('MIDTRANS_SERVER_KEY')); 

        if($signature_key != $json->signature_key){
            return abort('404');
        }

        if($json->transaction_status  == 'settlement'){
     
            Transaksi_event::where('order_id', $json->order_id)->update([
                'transaction_status' => $json->transaction_status
            ]);
           $data = Transaksi_event::join('users as s','s.id_anggota','=','m_transaksi_events.id_anggota')
                                ->join('data_users as du','s.id','=','du.id_users')
                                ->select('du.no_hp', 's.name','m_transaksi_events.*','s.id_anggota')
                                ->where('m_transaksi_events.order_id', $json->order_id)->first();

        $message = "🔵 Pembayaran Anda telah diterima!\n\n"
        . "Terima kasih telah melakukan pembayaran. Berikut adalah rincian transaksi Anda:\n\n"
        . "➡ Id Anggota: $data->id_anggota\n"
        . "➡ Nama: $data->name\n"
        . "➡ Id Transaksi: $data->order_id\n"
        . "➡ Tanggal Pembayaran: $data->transaction_time\n"
        . "➡ Jumlah Pembayaran: Rp. $data->gross_amount\n\n"
        . "Semua informasi tersebut telah dicatat dan diverifikasi. Harap simpan nomor referensi ini untuk referensi Anda. Jika Anda memiliki pertanyaan atau perlu bantuan lebih lanjut, hubungi kami di 088217784280.\n\n"
        . "Salam,\n"
        . "Maziltu Tholiban";
                       
           $status = DataPicker::sendWa($data->no_hp, $message);


           if($status){
            return response()->json([
                'success' => true,
                'message' => 'pembayaran berhasil!!',
                'data'    => 'null'
            ], 200);
           }else{
            return response()->json([
                'success' => false,
                'message' => 'pembayaran gagal!!',
                'data'    => 'null'
            ], 500);
           }
        }
    }

    function tambahTransaksiAdmin(Request $request)
    {
        $request->validate([
            'nama' => ['required'],
            'alamat' => ['required'],
            'tanggal_lahir' => ['required'],
            'tahun_masuk' => ['required'],
            'tahun_keluar' => ['required'],
            'niqobah' => ['required'],
            'tempat_lahir' => ['required'],
            'infak' => ['required'],
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

        $event = Event::where('id', $request->id_event )->first();
        $harga_grend = $request->infak;
        $hargaTotal = (int)str_replace(array("Rp. ", "."), "", $harga_grend);
        $hargaComper = (int)str_replace(array("Rp. ", "."), "", $event->harga);

        $data_tlp = DataUser::where(['no_hp' => $request->nomer_telpon])->count();
        $data_user_nama = user::where(['name' => $request->nama])->count();

        $file_status = $_FILES["foto"]["name"];
        if(($data_tlp == 0 && $data_user_nama == 0)){

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
                $status =  DataUser::insert([
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
            $status = Transaksi_event::insert([
                'id_event' => $request->id_event,
                'id_anggota' => $id_anggota,
                'gross_amount' =>  number_format($hargaTotal, 2, '.', ''),
                'payment_type' => 'admin',
                'transaction_status' => 'settlement',
                'order_id' => $id_transaksi,
                'updated_at' => date("Y-m-d H:i:s"),
                'created_at' => date("Y-m-d H:i:s"),
            ]);
            if ($status) {

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil!!',
                    'data'    => 'pendaftaran event berhasil'
                ], 200);
            }
            return response()->json([
                'success' => true,
                'message' => 'gagal!!',
                'data'    => 'pendaftaran event gagal'
            ], 400);
        }else{

            $data_user = DataUser::join('users', 'users.id', '=', 'data_users.id_users')
            ->select('data_users.*', 'users.name', 'users.id_anggota', 'users.id as id_users')
            ->where(function ($query) use ($request) {
                $query->where('data_users.no_hp', $request->nomer_telpon)
                      ->orWhere('users.name', 'like', '%' . $request->nama . '%');
            })
            ->first();
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


            if($statusPendaftaran->count() != 0){
                if ($statusPendaftaran->first()->transaction_status == 'settlement') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal!!',
                        'data'    => 'user sudah terdaftar dan pembayaran user sudah berhasil'
                    ], 200);
                }
            }
        
            $status = Transaksi_event::insert([
                'id_event' => $request->id_event,
                'id_anggota' => $data_user->id_anggota,
                'gross_amount' =>  number_format($hargaTotal, 2, '.', ''),
                'payment_type' => 'admin',
                'order_id' => $id_transaksi,
                'transaction_status' => 'settlement',
                'updated_at' => date("Y-m-d H:i:s"),
            ]);
            if ($status) {

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil!!',
                    'data'    => 'pendaftaran event berhasil'
                ], 200);
            }
            return response()->json([
                'success' => false,
                'message' => 'pendaftaran!!',
                'data'    => 'pendaftaran gagal'
            ], 401);

        }

    }

    function tambahTransaksiAnggota(Request $request)
    {
        $timestamp = now()->format('ymd'); // Mengambil tanggal dalam format YYMMDD
        $randomNumber = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT); // Menghasilkan angka acak 4 digit dengan leading zero jika diperlukan
        $id_transaksi = $timestamp . $randomNumber; 
        $status = Transaksi_event::insert([
            'id_event' => $request->id_event,
            'id_anggota' => $request->nama_anggota,
            'payment_type' => 'admin',
            'order_id' => $id_transaksi,
            'transaction_status' => 'offline',
            'updated_at' => date("Y-m-d H:i:s"),
        ]);

        if ($status) {

            return response()->json([
                'success' => true,
                'message' => 'berhasil!!',
                'data'    => 'pendaftaran event berhasil'
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'pendaftaran!!',
                'data'    => 'pendaftaran gagal'
            ], 401);
        }
    }

    function verifikasiPendaftar(Request $request)
    {
        $request->validate([
            'infak' => ['required'],
        ]);
    
        $harga_grend = $request->infak;
        $hargaTotal = (int)str_replace(array("Rp. ", "."), "", $harga_grend);
        $status = Transaksi_event::where(['id_event' => $request->id_event , 'id_anggota' => $request->id_anggota ])->update([
            'gross_amount' => $hargaTotal,
            'transaction_status' => 'settlement',
        ]);

        if($status){
            return response()->json([
                'success' => true,
                'message' => 'verifikasi!!',
                'data'    => 'verifikasi berhasil'
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'verifikasi!!',
                'data'    => 'verifikasi gagal'
            ], 401);
        }
    }

}
