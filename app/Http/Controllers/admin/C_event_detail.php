<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Tanggal_event;
use DataPicker;

class C_event_detail extends Controller
{
    function index(Request $request, $id)
    {
        $count = Event::where('id', $id)->count();

        if($count == 0){
            return abort(404);
        }
        $data = Event::where('id',$id)->first();
        \DataPicker::activitas_log('membuka event detail');
        return view('admin.detail_event',['data' => $data]);
    }

    function getData(Request $request)
    {
        $data = Tanggal_event::where('id_event', $request->id)->get();

        return response()->json([
            'success' => true,
            'message' => 'berhasil ambil data',
            'data'    => $data ,
            
        ],200);
    }

    function saveData(Request $request)
    {
        if ($request->full_day == 'true') {
            Tanggal_event::where('id', $request->id_event)->update([
                'set_jam' => 'seharian'
            ]);
            return response()->json([
                'success' => true,
                'message' => 'berhasil input data',
                'data'    => 'succes' 
            ],200);
        }else{
            if ($request->sampai_selesai == 'true') {
                Tanggal_event::where('id', $request->id_event)->update([
                    'set_jam' => 'dijam',
                    'jam_mulai' => $request->start_date,
                    'jam_selesai' => 'sampai selesai',
                ]);
            }else{
                Tanggal_event::where('id', $request->id_event)->update([
                    'set_jam' => 'dijam',
                    'jam_mulai' => $request->start_date,
                    'jam_selesai' => $request->end_date,
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => 'berhasil input data',
                'data'    => 'succes' 
            ],200);
        }
    }
}
