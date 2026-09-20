<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Activitas_log;
use App\Models\User;
use DataPicker;

class C_Aktivitas_log extends Controller
{
    function index()
    {
        \DataPicker::activitas_log('membuka log aktivitas anggota');
        return view('admin.aktivitas_user');
    }

    function getData()
    {
        $data = User::join('data_users','users.id','=','data_users.id_users')
            ->select('data_users.*','users.name as nama', 'users.email','users.id_anggota')
            ->where('data_users.is_active','1')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'berhasil ambil data',
            'data' => $data,
        ], 200);
    }

    function aktivitas(Request $request, $id)
    {
        $data = Activitas_log::join('users', 'users.id','=','activitas_logs.user_id')
        ->select('users.name as nama', 'activitas_logs.*')         
        ->orderBy('created_at','desc')           
        ->where(['user_id' => $id])->get();
        \DataPicker::activitas_log('membuka detail log aktivitas anggota');
        return view('admin.detail_aktivitas_log', ['data' => $data, 'id' => $id]);
    }

    function dataAktivitasLog(Request $request, $id)
    {

        $data = Activitas_log::join('users', 'users.id','=','activitas_logs.user_id')
        ->select('users.name as nama', 'activitas_logs.*')
        ->where('user_id', $id) // Ubah where menjadi kondisi langsung
        ->orderBy('activitas_logs.created_at', 'desc'); // Ubah 'created_at' menjadi 'activitas_logs.created_at'
    
        if (!empty($request->search['value'])) {
            $data->where(function ($query) use ($request) {
                $query->where('activitas_logs.subject', 'like', '%' . $request->search['value'] . '%')
                    ->orWhere('users.name', 'like', '%' . $request->search['value'] . '%');
            });
        }
        
        $recordsTotal = Activitas_log::count();
        $recordsFiltered = $data->count();
        
        $data = $data->skip($request->start)->take($request->length)->get();
        
        return response()->json([
            'draw' => $request->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ], 200);
    
    }
}
