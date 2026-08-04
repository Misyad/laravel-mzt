<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Berita;
use Illuminate\Support\Facades\File;
use DataPicker;

class C_Berita extends Controller
{
    function index()
    {
        \DataPicker::activitas_log('membuka tabel berita');
        return view('admin.tabel_berita');
    }

    function storeData(Request $request)
    {
        $request->validate([
            'judul' => ['required'],
            'slug' => ['required'],
            'deskripsi' => ['required'],
            'foto' => ['required','image','mimes:jpg,png,jpeg,gif,svg','max:1048'],
        ]);

        \DataPicker::activitas_log('membuat berita');
        $image_path = $request->file('foto')->store('image/berita', 'public');
        $slug = preg_replace('/\s+/', '-', $request->slug);
        $slug_count = Berita::where(['slug' => $slug])->count();
        if($slug_count == 0){
        Berita::insert([
            'judul' => $request->judul,
            'deskripsi' => $request->deskripsi,
            'slug' => $slug,
            'foto' => $image_path,
            'create_at' => auth()->user()->id_anggota,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'berhasil input data',
            'data'    => 'succes' 
        ],200);

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
        $data = Berita::join('users', 'users.id_anggota', '=' ,'beritas.create_at')
                    ->where('beritas.is_active', '1')
                    ->select('users.name as nama', 'beritas.*')
                    ->get();
        return response()->json([
            'success' => true,
            'message' => 'berhasil ambil data',
            'data'    => $data ,
            
        ],200);
    }
    function editData(Request $request)
    {
        $request->validate([
            'id_berita' => ['required'],
            'judul' => ['required'],
            'slug' => ['required'],
            'deskripsi' => ['required'],
            'foto' => ['image','mimes:jpg,png,jpeg,gif,svg','max:1048'],
        ]);

        \DataPicker::activitas_log('edit berita');
        $file_lama =  $request->foto_lama;
        $file_status = $_FILES["foto"]["name"];
        $slug = preg_replace('/\s+/', '-', $request->slug);
        $slug_count = Berita::where(['id' => $request->id_berita])->first();
        
        if($slug_count->slug == $slug ){

            if($file_status)
            {
                if(File::exists(public_path('storage/'.$file_lama))){
                    File::delete(public_path('storage/'.$file_lama));

                    $image_path = $request->file('foto')->store('image/berita', 'public');

                    Berita::where(['id' => $request->id_berita])->update([
                        'judul' => $request->judul,
                        'deskripsi' => $request->deskripsi,
                        'slug' => $slug,
                        'foto' => $image_path,
                        'edit_at' => auth()->user()->id_anggota,
                    ]);

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
            }else{
                Berita::where(['id' => $request->id_berita])->update([
                    'judul' => $request->judul,
                    'deskripsi' => $request->deskripsi,
                    'slug' => $slug,
                    'edit_at' => auth()->user()->id_anggota,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'berhasil update data',
                    'data'    => 'succes' 
                ],200);
                
            }

        }else{
            $slug_count = Berita::where(['slug' => $slug])->count();

            if($slug_count == 0){

                if($file_status)
                {
                    if(File::exists(public_path('storage/'.$file_lama))){
                        File::delete(public_path('storage/'.$file_lama));
    
                        $image_path = $request->file('foto')->store('image/berita', 'public');
    
                        Berita::where(['id' => $request->id_berita])->update([
                            'judul' => $request->judul,
                            'deskripsi' => $request->deskripsi,
                            'slug' => $slug,
                            'foto' => $image_path,
                            'edit_at' => auth()->user()->id_anggota,
                        ]);
    
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
                }else{
                    Berita::where(['id' => $request->id_berita])->update([
                        'judul' => $request->judul,
                        'deskripsi' => $request->deskripsi,
                        'slug' => $slug,
                        'edit_at' => auth()->user()->id_anggota,
                    ]);
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

    function deleteData(Request $request)
    {
        $request->validate([
            'id' => ['required'],
        ]);
        Berita::where(['id' => $request->id])->update([
            'is_active' => '0',
            'slug' => '',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'berhasil update data',
            'data'    => 'succes' 
        ],200);
        
    }
}
