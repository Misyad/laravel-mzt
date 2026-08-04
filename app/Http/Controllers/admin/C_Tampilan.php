<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Info_pesantren;
use Illuminate\Support\Facades\File;
use App\Models\Tentang_mzt;
use App\Models\Carosel;
use DataPicker;

class C_Tampilan extends Controller
{
    function carosel()
    {
        $data = Carosel::where('id','1')->first();
        \DataPicker::activitas_log('membuka tampilan carosel');
        return view('admin.view_carosel',['data' => $data]);
    }
    function tentanPondok()
    {
        $data = Info_pesantren::where('id','1')->first();
        \DataPicker::activitas_log('membuka tampilan tentang pesantren');
        return view('admin.view_tenteng_pondok',['data' => $data]);
    }
    function tentanMzt()
    {
        $data = Tentang_mzt::where('id','1')->first();
        \DataPicker::activitas_log('membuka tampilan tentang mzt');
        return view('admin.view_tenteng_mzt',['data' => $data]);
    }

    function simpanDataPesantren(Request $request)
    {
        $request->validate([
            'judul' => ['required'],
            'deskripsi' => ['required'],
            'alamat' => ['required'],
            'foto' => ['required','image','mimes:jpg,png,jpeg,gif,svg','max:2048'],
        ]);


        $image_path = $request->file('foto')->store('image/pesantren', 'public');

        Info_pesantren::insert([
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'alamat' => $request->alamat,
            'telpon' => $request->no_tlp,
            'email' => $request->email,
            'foto' => $image_path,
        ]);
     
        return response()->json([
            'success' => true,
            'message' => 'berhasil simpan data',
            'data'    => 'succes' 
        ],200);
    }

    function simpanDataPesantren2(Request $request)
    {
        $file_status = $_FILES["foto"]["name"];
        $file_lama =  $request->foto_lama;
        $request->validate([
            'judul' => ['required'],
            'deskripsi' => ['required'],
            'alamat' => ['required'],
            'foto' => ['image','mimes:jpg,png,jpeg,gif,svg','max:2048'],
        ]);
        \DataPicker::activitas_log('edit pesantren');

        
        if($file_status){
            if (File::exists(public_path('storage/'.$file_lama))) {
                File::delete(public_path('storage/'.$file_lama));

                $image_path = $request->file('foto')->store('image/pesantren', 'public');

                if($image_path){

                Info_pesantren::where('id',$request->id)->update([
                    'judul' => $request->judul,
                    'deskripsi' => $request->deskripsi,
                    'alamat' => $request->alamat,
                    'telpon' => $request->no_tlp,
                    'email' => $request->email,
                    'foto' => $image_path,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'berhasil simpan data',
                    'foto' => $image_path,
                    'data'    => 'succes' 
                ],200);
            }else{
                return response()->json([
                    'success' => true,
                    'message' => 'update data gagal',
                    'data'    => 'Gagal' 
                ],500);
            }

            }else{
                return response()->json([
                    'success' => true,
                    'message' => 'update data gagal',
                    'data'    => 'Gagal' 
                ],500);
            }
        }else{
            Info_pesantren::where('id',$request->id)->update([
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'alamat' => $request->alamat,
                'telpon' => $request->no_tlp,
                'email' => $request->email,
                
            ]);
            return response()->json([
                'success' => true,
                'message' => 'berhasil simpan data',
                'data'    => 'succes' 
            ],200);
        }
    }
    function simpanDataMzt(Request $request)
    {
        $file_status = $_FILES["foto"]["name"];
        $file_lama =  $request->foto_lama;
        $request->validate([
            'judul' => ['required'],
            'deskripsi' => ['required'],
            'alamat' => ['required'],
            'foto' => ['image','mimes:jpg,png,jpeg,gif,svg','max:2048'],
        ]);
        \DataPicker::activitas_log('edit mzt');
        if($file_status){
            if (File::exists(public_path('storage/'.$file_lama))) {
                File::delete(public_path('storage/'.$file_lama));

                $image_path = $request->file('foto')->store('image/mzt', 'public');

                if($image_path){
                    Tentang_mzt::where('id',$request->id)->update([
                        'judul' => $request->judul,
                        'deskripsi' => $request->deskripsi,
                        'alamat' => $request->alamat,
                        'telpon' => $request->no_tlp,
                        'email' => $request->email,
                        'foto' => $image_path,
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'berhasil simpan data',
                        'foto' => $image_path,
                        'data'    => 'succes' 
                    ],200);
                }else{
                    return response()->json([
                        'success' => true,
                        'message' => 'update data gagal',
                        'data'    => 'Gagal' 
                    ],500);
                }

              

            }else{
                return response()->json([
                    'success' => true,
                    'message' => 'update data gagal',
                    'data'    => 'Gagal' 
                ],500);
            }
        }else{
            Tentang_mzt::where('id',$request->id)->update([
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'alamat' => $request->alamat,
                'telpon' => $request->no_tlp,
                'email' => $request->email,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'berhasil simpan data',
                'data'    => 'succes' 
            ],200);
        }
    }
    function simpanCarosel(Request $request)
    {
        $file_status = $_FILES["foto"]["name"];
        $file_lama =  $request->foto_lama;
        $request->validate([
            'foto' => ['required','image','mimes:jpg,png,jpeg,gif,svg','max:2048'],
        ]);
        \DataPicker::activitas_log('edit carosel');
        if($file_status){
            if (File::exists(public_path('storage/'.$file_lama))) {
                File::delete(public_path('storage/'.$file_lama));

                $image_path = $request->file('foto')->store('image/carosel', 'public');

                Carosel::where('id',$request->id)->update([
                    'foto' => $image_path,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'berhasil simpan data',
                    'foto' => $image_path,
                    'data'    => 'succes' 
                ],200);

            }else{
                $image_path = $request->file('foto')->store('image/carosel', 'public');
             
                Carosel::where('id',$request->id)->update([
                    'foto' => $image_path,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'berhasil simpan data',
                    'foto' => $image_path,
                    'data'    => 'succes' 
                ],200);
            }
        }else{
            return response()->json([
                'success' => true,
                'message' => 'berhasil simpan data',
                'data'    => 'succes' 
            ],200);
        }
    }
}
