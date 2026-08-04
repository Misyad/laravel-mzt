<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DataPicker;
use App\Models\Event;
use App\Models\Tanggal_event;
use Illuminate\Support\Facades\File;
use App\Models\Transaksi_event;
use DateTime;


class C_Event extends Controller
{
    function index()
    {
        \DataPicker::activitas_log('membuka tabel event');
        return view('admin.event');

    }

    function storeData(Request $request)
    {

        
        $file_status = $_FILES["banner"]["name"];

        $request->validate([
            'judul' => ['required'],
            'deskripsi' => ['required'],
            'tanggal' => ['required'],
            'slug' => ['required'],
            'harga' => ['required'],
            'lokasi' => ['required'],
            'banner' => ['image','mimes:jpg,png,jpeg,gif,svg','max:1048'],
        ]);

        \DataPicker::activitas_log('membuat event');

        $regex = '/\s*-\s*/';
        $dates = preg_split($regex,  $request->tanggal);
        $start_date = trim($dates[0]);
        $end_date = trim($dates[1]);
        $start_date = DateTime::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
        $end_date = DateTime::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');

        $slug = preg_replace('/\s+/', '-', $request->slug);


        $slug_count = Event::where(['slug' => $slug])->count();

        if($slug_count == 0){
            if($file_status){
                $image_path = $request->file('banner')->store('image/event', 'public');
    

                $id = Event::insertGetId([
                    'judul_event' => $request->judul,
                    'deskripsi' => $request->deskripsi,
                    'lokasi' => $request->lokasi,
                    'harga' => $request->harga,
                    'tanggal' => $request->tanggal,
                    'tanggal_selesai' => $end_date,
                    'tanggal_mulai' => $start_date,
                    'slug' => $slug,
                    'banner' => $image_path,
                ]);

                $data_array = DataPicker::dateRange($request->tanggal);

                $data_tanggal = array_map(function($v) use($id) {
                    return [
                        'id_event' => $id,
                        'tanggal' => $v[0],
                    ];
                },  $data_array);

                Tanggal_event::insert($data_tanggal);

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil input data',
                    'data'    => 'succes' 
                ],200);
            }else {

                $id = Event::insertGetId([
                    'judul_event' => $request->judul,
                    'deskripsi' => $request->deskripsi,
                    'tanggal' => $request->tanggal,
                    'lokasi' => $request->lokasi,
                    'harga' => $request->harga,
                    'tanggal_selesai' => $end_date,
                    'tanggal_mulai' => $start_date,
                    'slug' => $slug,
                ]);

                $data_array = DataPicker::dateRange($request->tanggal);

                $data_tanggal = array_map(function($v) use($id) {
                    return [
                        'id_event' => $id,
                        'tanggal' => $v[0],
                    ];
                },  $data_array);

                Tanggal_event::insert($data_tanggal);

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil input data',
                    'data'    => 'succes' 
                ],200);
            }
        }else{
            return response()->json([
                'success' => true,
                'message' => 'slug sudah ada',
                'data'    => 'succes' 
            ],500);
        }

    }

    function getData()
    {
        $data = Event::where(['is_active' => '1'])->get();

        return response()->json([
            'success' => true,
            'message' => 'berhasil ambil data',
            'data'    => $data ,
            
        ],200);
    }

    function editData(Request $request)
    {
        $file_status = $_FILES["banner"]["name"];
        $file_lama =  $request->foto_lama;

        \DataPicker::activitas_log('edit event');
        $request->validate([
            'judul' => ['required'],
            'deskripsi' => ['required'],
            'tanggal' => ['required'],
            'slug' => ['required'],
            'harga' => ['required'],
            'lokasi' => ['required'],
            'banner' => ['image','mimes:jpg,png,jpeg,gif,svg','max:1048'],
        ]);

        $regex = '/\s*-\s*/';
        $dates = preg_split($regex,  $request->tanggal);
        $start_date = trim($dates[0]);
        $end_date = trim($dates[1]);
        $start_date = DateTime::createFromFormat('d/m/Y', $start_date)->format('Y-m-d');
        $end_date = DateTime::createFromFormat('d/m/Y', $end_date)->format('Y-m-d');

        $slug = preg_replace('/\s+/', '-', $request->slug);
        
        $slug_count = Event::where(['id' => $request->id_event])->first();
        $id =  $request->id_event;

        if($slug_count->slug == $slug ){

            if($file_status)
            {
                if($file_lama == 'null'){
                    $image_path = $request->file('banner')->store('image/event', 'public');

                    Event::where(['id' => $request->id_event])->update([
                        'judul_event' => $request->judul,
                        'deskripsi' => $request->deskripsi,
                        'tanggal' => $request->tanggal,
                        'lokasi' => $request->lokasi,
                        'harga' => $request->harga,
                        'tanggal_selesai' => $end_date,
                        'tanggal_mulai' => $start_date,
                        'slug' => $slug,
                        'banner' => $image_path,
                    ]);

                    Tanggal_event::where('id_event', $id)->delete();

                    $data_array = DataPicker::dateRange($request->tanggal);

                    $data_tanggal = array_map(function($v) use($id) {
                        return [
                            'id_event' => $id,
                            'tanggal' => $v[0],
                        ];
                    },  $data_array);
        
                    Tanggal_event::insert($data_tanggal);

                    return response()->json([
                        'success' => true,
                        'message' => 'berhasil update data',
                        'data'    => 'succes' 
                    ],200);

                }else{
                    if(File::exists(public_path('storage/'.$file_lama))){
                        File::delete(public_path('storage/'.$file_lama));

                        $image_path = $request->file('banner')->store('image/event', 'public');

                        Event::where(['id' => $request->id_event])->update([
                            'judul_event' => $request->judul,
                            'deskripsi' => $request->deskripsi,
                            'tanggal' => $request->tanggal,
                            'lokasi' => $request->lokasi,
                            'harga' => $request->harga,
                            'tanggal_selesai' => $end_date,
                            'tanggal_mulai' => $start_date,
                            'slug' => $slug,
                            'banner' => $image_path,
                        ]);
        
                        Tanggal_event::where('id_event', $id)->delete();
        
                        $data_array = DataPicker::dateRange($request->tanggal);
        
                        $data_tanggal = array_map(function($v) use($id) {
                            return [
                                'id_event' => $id,
                                'tanggal' => $v[0],
                            ];
                        },  $data_array);
            
                        Tanggal_event::insert($data_tanggal);
        
                        return response()->json([
                            'success' => true,
                            'message' => 'berhasil update data',
                            'data'    => 'succes' 
                        ],200);

                    }else{
                        return response()->json([
                            'success' => true,
                            'message' => 'update data gagal',
                            'data'    => 'Gagal' 
                        ],500);
                    }
                }
            }else{
                Event::where(['id' => $request->id_event])->update([
                    'judul_event' => $request->judul,
                    'deskripsi' => $request->deskripsi,
                    'tanggal' => $request->tanggal,
                    'lokasi' => $request->lokasi,
                    'harga' => $request->harga,
                    'tanggal_selesai' => $end_date,
                    'tanggal_mulai' => $start_date,
                    'slug' => $slug,
                ]);

                Tanggal_event::where('id_event', $id)->delete();

                $data_array = DataPicker::dateRange($request->tanggal);

                $data_tanggal = array_map(function($v) use($id) {
                    return [
                        'id_event' => $id,
                        'tanggal' => $v[0],
                    ];
                },  $data_array);

                Tanggal_event::insert($data_tanggal);

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil update data',
                    'data'    => 'succes' 
                ],200);
            }

        }else{
            $slug_count = Event::where(['slug' => $slug])->count();

            if($slug_count == 0){

            if($file_status)
            {
                if($file_lama == 'null'){
                    $image_path = $request->file('banner')->store('image/event', 'public');

                    Event::where(['id' => $request->id_event])->update([
                        'judul_event' => $request->judul,
                        'deskripsi' => $request->deskripsi,
                        'tanggal' => $request->tanggal,
                        'lokasi' => $request->lokasi,
                        'harga' => $request->harga,
                        'tanggal_selesai' => $end_date,
                        'tanggal_mulai' => $start_date,
                        'slug' => $slug,
                        'banner' => $image_path,
                    ]);

                    Tanggal_event::where('id_event', $id)->delete();

                    $data_array = DataPicker::dateRange($request->tanggal);

                    $data_tanggal = array_map(function($v) use($id) {
                        return [
                            'id_event' => $id,
                            'tanggal' => $v[0],
                        ];
                    },  $data_array);
        
                    Tanggal_event::insert($data_tanggal);

                    return response()->json([
                        'success' => true,
                        'message' => 'berhasil update data',
                        'data'    => 'succes' 
                    ],200);

                }else{
                    if(File::exists(public_path('storage/'.$file_lama))){
                        File::delete(public_path('storage/'.$file_lama));

                        $image_path = $request->file('banner')->store('image/event', 'public');

                        Event::where(['id' => $request->id_event])->update([
                            'judul_event' => $request->judul,
                            'deskripsi' => $request->deskripsi,
                            'tanggal' => $request->tanggal,
                            'lokasi' => $request->lokasi,
                            'harga' => $request->harga,
                            'tanggal_selesai' => $end_date,
                            'tanggal_mulai' => $start_date,
                            'slug' => $slug,
                            'banner' => $image_path,
                        ]);
        
                        Tanggal_event::where('id_event', $id)->delete();
        
                        $data_array = DataPicker::dateRange($request->tanggal);
        
                        $data_tanggal = array_map(function($v) use($id) {
                            return [
                                'id_event' => $id,
                                'tanggal' => $v[0],
                            ];
                        },  $data_array);
            
                        Tanggal_event::insert($data_tanggal);
        
                        return response()->json([
                            'success' => true,
                            'message' => 'berhasil update data',
                            'data'    => 'succes' 
                        ],200);

                    }else{
                        return response()->json([
                            'success' => true,
                            'message' => 'update data gagal',
                            'data'    => 'Gagal' 
                        ],500);
                    }
                }
            }else{
                Event::where(['id' => $request->id_event])->update([
                    'judul_event' => $request->judul,
                    'deskripsi' => $request->deskripsi,
                    'tanggal' => $request->tanggal,
                    'lokasi' => $request->lokasi,
                    'harga' => $request->harga,
                    'tanggal_selesai' => $end_date,
                    'tanggal_mulai' => $start_date,
                    'slug' => $slug,
                ]);

                Tanggal_event::where('id_event', $id)->delete();

                $data_array = DataPicker::dateRange($request->tanggal);

                $data_tanggal = array_map(function($v) use($id) {
                    return [
                        'id_event' => $id,
                        'tanggal' => $v[0],
                    ];
                },  $data_array);

                Tanggal_event::insert($data_tanggal);

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil update data',
                    'data'    => 'succes' 
                ],200);
            }
             
            }else{
                return response()->json([
                    'success' => true,
                    'message' => 'slug sudah ada',
                    'data'    => 'succes' 
                ],500);
            }
        }
    }

    function delete(Request $request)
    {
        $status = Transaksi_event::where(['id_event' => $request->id])->count();

        if($status != 0 ){
            return response()->json([
                'success' => false,
                'message' => 'Gagal hapus event, event sudah memiliki tr',
                'data'    => 'error' 
            ],200);
        }
        Event::where(['id' => $request->id])->update([
            'is_active' => '0',
            'slug' => '',
        ]);

        \DataPicker::activitas_log('hapus event');
        return response()->json([
            'success' => true,
            'message' => 'berhasil hapus data',
            'data'    => 'success' 
        ],200);
    }   
}
