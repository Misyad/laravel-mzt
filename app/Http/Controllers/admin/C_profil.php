<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use App\Models\RoleUser;
use App\Models\DataUser;
use DNS1D;
use DataPicker;
use Image;

class C_profil extends Controller
{
    function index(Request $request)
    {
        $data = User::join('data_users','users.id','=','data_users.id_users')
        ->select('data_users.*','users.name as nama', 'users.email')
        ->where(['data_users.is_active' => '1', 'users.id' => auth()->user()->id])
        ->first();


        \DataPicker::activitas_log('membuka halaman profil');
        return view('admin.profil',['profil' => $data]);
    }

    function saveData(Request $request)
    {
        $file_status = $_FILES["foto"]["name"];

        \DataPicker::activitas_log('merubah profil');
        $request->validate([
            'id_users' => ['required'],
            'nama' => ['required'],
            'alamat' => ['required'],
            'no_hp' => ['required'],
            'pekerjaan' => ['required'],
            'tanggal_lahir' => ['required'],
            'tahun_masuk' => ['required'],
            'tahun_keluar' => ['required'],
            'foto' => ['image','mimes:jpg,png,jpeg,gif,svg','max:1048'],
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

        if($data_diri->jatah_edit == 3){
            $request->session()->flash('error2', 'gagal simpan data');
           
            return redirect()->back();
        }
        if($file_status){
            
            if(File::exists(public_path('storage/'.$file_lama))){
            // if(false){

               File::delete(public_path('storage/'.$file_lama));
               File::delete(public_path('storage/'.$barcode_lama));

               $image_path = $request->file('foto')->store('image/anggota', 'public');
               $image = Image::make(storage_path('app/public/' . $image_path));
               $image->resize(300, 400); // Mengubah ukuran gambar
               $image->save();


               if($request->password){
                    User::where('id', $id)->update([
                        'name' => $request->nama,
                        'email' => $request->email,
                        'password' => Hash::make($request->password),
                        'jatah_edit' => $data_diri->jatah_edit+1,
                    ]);
               }else{
                    User::where('id', $id)->update([
                        'name' => $request->nama,
                        'email' => $request->email,
                        'jatah_edit' => $data_diri->jatah_edit+1,
                    ]);
               }
      

               DataUser::where('id_users', $id)->update([
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->no_hp,
                    'pekerjaan' => $request->pekerjaan,
                    'foto' => $image_path,
               ]);

               $request->session()->flash('sukses', 'berhasil!');
               return redirect()->back();

            }else{
                
               $image_path = $request->file('foto')->store('image/anggota', 'public');
               $image = Image::make(storage_path('app/public/' . $image_path));
               $image->resize(300, 400); // Mengubah ukuran gambar
               $image->save();


               if($request->password){
                    User::where('id', $id)->update([
                        'name' => $request->nama,
                        'email' => $request->email,
                        'password' => Hash::make($request->password),
                        'jatah_edit' => $data_diri->jatah_edit+1,
                    ]);
               }else{
                    User::where('id', $id)->update([
                        'name' => $request->nama,
                        'email' => $request->email,
                        'jatah_edit' => $data_diri->jatah_edit+1,
                    ]);
               }
      

               DataUser::where('id_users', $id)->update([
                    'alamat' => $request->alamat,
                    'niqobah' => $request->niqobah,
                    'no_hp' => $request->no_hp,
                    'pekerjaan' => $request->pekerjaan,
                    'foto' => $image_path,
               ]);

               $request->session()->flash('sukses', 'berhasil!');
               return redirect()->back();
            }

        }else{

            if($request->password){
                User::where('id', $id)->update([
                    'name' => $request->nama,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'jatah_edit' => $data_diri->jatah_edit+1,
                ]);
           }else{
                User::where('id', $id)->update([
                    'name' => $request->nama,
                    'email' => $request->email,
                    'jatah_edit' => $data_diri->jatah_edit+1,
                ]);
           }

        DataUser::where('id_users', $id)->update([
            'alamat' => $request->alamat,
            'niqobah' => $request->niqobah,
            'no_hp' => $request->no_hp,
            'pekerjaan' => $request->pekerjaan,
       ]);

            $request->session()->flash('sukses', 'berhasil!');
             return redirect()->back();
        }

    }
}
