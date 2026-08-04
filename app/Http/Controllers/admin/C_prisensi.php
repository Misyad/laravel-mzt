<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Tanggal_event;
use App\Models\User;
use App\Models\DataUser;
use App\Models\Prisensi_kehadiran;
use DataPicker;

class C_prisensi extends Controller
{

    function tabel_prisensi()
    {
        \DataPicker::activitas_log('membuka prisensi');
        return view('admin.tabel_event_prisensi');
    }

    function index(Request $request, $id, $id2)
    {
        $count = Event::where('id', $id)->count();

        if($count == 0){
            return abort(404);
        }

        return view('admin.prisensi',['id_event' => $id, 'id_tanggal' => $id2]);
    }
    function getDataUser(Request $request)
    {
        $request->validate([
            'id' => ['required'],
        ]);


        $count = User::where('id_anggota', $request->id)->count();

        if ($count == 0) {
        
            return response()->json([
                'success' => true,
                'message' => 'data tidak ditemukan',
                'data'    => 'gagal' ,
            ],500);

        }else{
            $data = User::join('data_users','users.id','=','data_users.id_users')
            ->select('data_users.*','users.name as nama', 'users.email', 'users.id_anggota')
            ->where(['data_users.is_active' =>'1', 'users.id_anggota' => $request->id])
            ->get();
    
            return response()->json([
                'success' => true,
                'message' => 'berhasil ambil data',
                'data'    => $data ,
                
            ],200);
        }

    }

    function sendData(Request $request)
    {

        \DataPicker::activitas_log('melakukan prisensi');
       $data_count = Prisensi_kehadiran::where([
            'id_event' => $request->id_event,
            'id_tanggal' => $request->id_tanggal,
            'id_user' => $request->id_users,
                                ])->count();

        if( $data_count == 0){
            Prisensi_kehadiran::insert([
                'id_event' => $request->id_event,
                'id_tanggal' => $request->id_tanggal,
                'id_anggota' => $request->id_anggota_2,
                'id_user' => $request->id_users,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'berhasil simpan',
                'data'    => 'succes' 
            ],200);
        }else{
            return response()->json([
                'success' => true,
                'message' => 'data sudah ada',
                'data'    => 'succes' 
            ],500);
        }
    
    }

    function getDataTabel(Request $request)
    {
        $data = Prisensi_kehadiran::join('users', 'users.id', '=','prisensi_kehadiran.id_user')
                        ->join('data_users', 'data_users.id_users', '=','prisensi_kehadiran.id_user')
                        ->select('users.name as nama', 'users.id_anggota', 'prisensi_kehadiran.tanggal_kehadiran','prisensi_kehadiran.jam_kehadiran')
                        ->where(['prisensi_kehadiran.id_event' => $request->id_event, 'prisensi_kehadiran.id_tanggal' => $request->id_tanggal])
                        ->get();
        
                        return response()->json([
                            'success' => true,
                            'message' => 'berhasil ambil data',
                            'data'    => $data ,
                            
                        ],200);
    }
}
