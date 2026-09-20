<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\RoleUser;
use App\Models\User;
use App\Models\DataUser;
use App\Models\HakAksesRole;
use DNS1D;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use App\Support\MemberManagement;
Use PDF;
use DataPicker;
use Image;




class C_Anggota extends Controller
{
    function tabelAnggota()
    {
        Gate::forUser(auth()->user())->authorize('writeMember', MemberManagement::class);

        $roles = RoleUser::orderBy('nama_role', 'asc')->get();
        $roles_count = RoleUser::count();
        \DataPicker::activitas_log('membuka tabel anggota');
        return view('admin.tabel_anggota',['roles' => $roles,'roles_count' => $roles_count ]);
    }

    function storeData(Request $request)
    {
        Gate::forUser($request->user())->authorize('writeMember', MemberManagement::class);

        return response()->json([
            'success' => false,
            'code' => 'ACCOUNT_PROVISIONING_DISABLED',
            'message' => 'Pembuatan akun baru tidak tersedia.',
        ], 405);
    }

    function getData()
    {
        Gate::forUser(auth()->user())->authorize('writeMember', MemberManagement::class);

        $data = User::join('data_users','users.id','=','data_users.id_users')
            ->select('data_users.*','users.name as nama', 'users.email','users.id_anggota')
            ->where('data_users.is_active','1')
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'berhasil ambil data',
                'data'    => $data ,
                
            ],200);

    }

    function getDataHakakses(Request $request)
    {
        Gate::forUser($request->user())->authorize('manageAccounts', MemberManagement::class);

       $data = HakAksesRole::where('id_users', $request->id)
                    ->get();
        return response()->json([
            'success' => true,
            'message' => 'berhasil ambil data',
            'data'    => $data ,
            
        ],200);
    }

    function editData(Request $request)
    {
        Gate::forUser($request->user())->authorize('writeMember', MemberManagement::class);

        $file_status = $request->hasFile('foto');

        $request->validate([
            'id_users' => ['required'],
            'nama' => ['required'],
            'alamat' => ['required'],
            'no_hp' => ['required'],
            'pekerjaan' => ['required'],
            'tanggal_lahir' => ['required'],
            'tahun_masuk' => ['required'],
            'tahun_keluar' => ['required'],
            'foto' => ['nullable', 'image','mimes:jpg,png,jpeg,gif,svg','max:1048'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ]);

        
        $id = $request->id_users;

        $data_diri = User::where('id', $id)->first();
        $prefix = substr($data_diri->id_anggota, 0, 4);
        $file_lama = $request->foto_lama;
        $barcode_lama = $request->barcode;
        $tangal_lahir = date("dm", strtotime($request->tanggal_lahir));
        $tahun = substr(date("Y", strtotime($request->tanggal_lahir)),-2);
        $tahun_masuk = substr(date("Y", strtotime($request->tahun_masuk)),-2);
        $tahun_keluar = substr(date("Y", strtotime($request->tahun_keluar)),-2);
        $id_anggota = $prefix.$tahun.$tahun_masuk.$tahun_keluar;
        \DataPicker::activitas_log('edit anggota');

        if($file_status){
            
            if(File::exists(public_path('storage/'.$file_lama))){

               File::delete(public_path('storage/'.$file_lama));
               File::delete(public_path('storage/'.$barcode_lama));

               $barcode = \DNS1D::getBarcodePNG($id_anggota, 'C39');
               $filename = 'barcode-' . $id_anggota . '.png';
               $image_path_barcode = 'image/barcode/'.$filename;
               Storage::disk('public')->put('image/barcode/' . $filename, base64_decode($barcode));

               $image_path = $request->file('foto')->store('image/anggota', 'public');
               $image = Image::make(storage_path('app/public/' . $image_path));
               $image->resize(300, 400); // Mengubah ukuran gambar
               $image->save();


               User::where('id', $id)->update([
                    'id_anggota' => $id_anggota,
                    'name' => $request->nama,
                    'email' => $request->email,
               ]);
      

               DataUser::where('id_users', $id)->update([
                    'barcode' => $image_path_barcode,
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->no_hp,
                    'pekerjaan' => $request->pekerjaan,
                    'tempat_lahir' => $request->tempat_lahir,
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->tanggal_lahir)),
                    'tahun_masuk' => date("Y-m-d", strtotime($request->tahun_masuk)),
                    'tahun_keluar' => date("Y-m-d", strtotime($request->tahun_keluar)),
                    'foto' => $image_path,
               ]);

               HakAksesRole::where('id_users', $id)->delete();
               if(isset($request->hak_akses)){
                        
                        $roles = array_map(function($v) use($id){
                            return [
                                'id_users' => $id,
                                'nama_role' => $v,
                            ];
                        }, $request->hak_akses);

                        $a=array("id_users"=>$id,"nama_role"=>"profil");
                        array_push($roles,$a);

                        HakAksesRole::insert($roles);
                 }else{

                    $roles = ["id_users"=>$id,"nama_role"=>"profil"];
                    
                    HakAksesRole::insert($roles);
                }
          

                return response()->json([
                    'success' => true,
                    'message' => 'berhasil input data 1',
                    'data'    => 'succes' 
                ],200);

            }else{
                File::delete(public_path('storage/'.$barcode_lama));

                $barcode = \DNS1D::getBarcodePNG($id_anggota, 'C39');
                $filename = 'barcode-' . $id_anggota . '.png';
                $image_path_barcode = 'image/barcode/'.$filename;
                Storage::disk('public')->put('image/barcode/' . $filename, base64_decode($barcode));
 
                $image_path = $request->file('foto')->store('image/anggota', 'public');
                $image = Image::make(storage_path('app/public/' . $image_path));
                $image->resize(300, 400); // Mengubah ukuran gambar
                $image->save();
 
 
                User::where('id', $id)->update([
                     'id_anggota' => $id_anggota,
                     'name' => $request->nama,
                     'email' => $request->email,
                ]);
       
 
                DataUser::where('id_users', $id)->update([
                     'barcode' => $image_path_barcode,
                     'alamat' => $request->alamat,
                     'niqobah' => $request->niqobah,
                     'no_hp' => $request->no_hp,
                     'pekerjaan' => $request->pekerjaan,
                     'tempat_lahir' => $request->tempat_lahir,
                     'tanggal_lahir' => date("Y-m-d", strtotime($request->tanggal_lahir)),
                     'tahun_masuk' => date("Y-m-d", strtotime($request->tahun_masuk)),
                     'tahun_keluar' => date("Y-m-d", strtotime($request->tahun_keluar)),
                     'foto' => $image_path,
                ]);
 
                HakAksesRole::where('id_users', $id)->delete();
                if(isset($request->hak_akses)){
                         
                         $roles = array_map(function($v) use($id){
                             return [
                                 'id_users' => $id,
                                 'nama_role' => $v,
                             ];
                         }, $request->hak_akses);
 
                         $a=array("id_users"=>$id,"nama_role"=>"profil");
                         array_push($roles,$a);
 
                         HakAksesRole::insert($roles);
                  }else{
 
                     $roles = ["id_users"=>$id,"nama_role"=>"profil"];
                     
                     HakAksesRole::insert($roles);
                 }
           
 
                 return response()->json([
                     'success' => true,
                     'message' => 'berhasil input data 1',
                     'data'    => 'succes' 
                 ],200);
            }

        }else{

            File::delete(public_path('storage/'.$barcode_lama));
            
            $barcode = \DNS1D::getBarcodePNG($id_anggota, 'C39');
            $filename = 'barcode-' . $id_anggota . '.png';
            $image_path_barcode = 'image/barcode/'.$filename;
            Storage::disk('public')->put('image/barcode/' . $filename, base64_decode($barcode));

            User::where('id', $id)->update([
                'id_anggota' => $id_anggota,
                'name' => $request->nama,
                'email' => $request->email,
            ]);

        DataUser::where('id_users', $id)->update([
            'barcode' => $image_path_barcode,
            'alamat' => $request->alamat,
            'niqobah' => $request->niqobah,
            'no_hp' => $request->no_hp,
            'pekerjaan' => $request->pekerjaan,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => date("Y-m-d", strtotime($request->tanggal_lahir)),
            'tahun_masuk' => date("Y-m-d", strtotime($request->tahun_masuk)),
            'tahun_keluar' => date("Y-m-d", strtotime($request->tahun_keluar)),
       ]);

            HakAksesRole::where('id_users', $id)->delete();
            if(isset($request->hak_akses)){
                    
                    $roles = array_map(function($v) use($id){
                        return [
                            'id_users' => $id,
                            'nama_role' => $v,
                        ];
                    }, $request->hak_akses);

                    $a=array("id_users"=>$id,"nama_role"=>"profil");
                    array_push($roles,$a);

                    HakAksesRole::insert($roles);
            }else{

                $roles = ["id_users"=>$id,"nama_role"=>"profil"];
                
                HakAksesRole::insert($roles);
            }

            return response()->json([
                'success' => true,
                'message' => 'berhasil input data 2',
                'data'    => 'succes' 
            ],200);
        }
    }

    function deleteData(Request $request)
    {
        Gate::forUser($request->user())->authorize('writeMember', MemberManagement::class);

        return response()->json([
            'success' => false,
            'code' => 'ACCOUNT_STATUS_ROUTE_REQUIRED',
            'message' => 'Perubahan status akun harus melalui endpoint status akun.',
        ], 405);
    }

    function exportPdf(Request $request, $id)
    {
        Gate::forUser($request->user())->authorize('viewCards', MemberManagement::class);

        $data = DataUser::query()
            ->with('user')
            ->where('id_users', $id)
            ->where('is_active', '1')
            ->whereHas('user', fn ($query) => $query->where('is_active', '1'))
            ->first();
        $data2 = $data?->user;
        
        if (!$data || !$data2) {
            return abort(404, 'Data anggota tidak ditemukan');
        }

        
        $images = $data->barcode;
        $nama = $data2->name;


        // Mendapatkan path file
        // $filePath = public_path('/storage/'. $images);
        // $image = File::get($filePath);
        // $imageData = base64_encode($image);

        // Mendapatkan path file
        $filePath2 = '/storage/'. $data->foto;
        $filePath3 = '/assets/KTA Musan FixArtboard 1.jpg';
        $cek = public_path('storage/'. $data->foto);
        if(empty($data->foto) || !File::exists($cek)){
            $filePath2 = '/assets/avatar-1.png';
        }

        // dd($filePath2);
        // $image2 = File::get($filePath2);
        // $image3 = File::get($filePath3);
        // $imageData2 = base64_encode($image2);
        // $imageData3 = base64_encode($image3);




        // print_r($backgournd_css);
        // die;

        $data = [
            'nama' => $nama, 
            'id_anggota' => $data2->id_anggota, 
            'alamat' => $data->alamat, 
            'niqobah' => $data->niqobah, 
            'tahun_masuk' => date('Y', strtotime( $data->tahun_masuk)), 
            'tahun_keluar' => date('Y', strtotime( $data->tahun_keluar)), 
            'bracode' => $data->barcode, 
            'profil' => $filePath2, 
        ];
          

        return view('kta', $data);
        // $pdf = PDF::loadView('kta', $data)-> setPaper ( [ 0 , 0 , 360 , 225 ] , 'portrait' );
        // // $pdf = PDF::loadView('kta', $data)-> setPaper ( 'A4', 'landscape' );

        // return $pdf->stream('kta.pdf');
        // return $pdf->download('kta.pdf');

    }
}


