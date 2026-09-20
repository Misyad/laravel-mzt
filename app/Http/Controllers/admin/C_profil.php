<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\DataUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
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
        \DataPicker::activitas_log('merubah profil');
        $request->validate([
            'nama' => ['required'],
            'email' => ['nullable', 'email', 'max:255'],
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

        $data_diri = $request->user();
        $profil = DataUser::where('id_users', $data_diri->id)->firstOrFail();

        if ((int) $data_diri->jatah_edit >= 3) {
            $request->session()->flash('error2', 'gagal simpan data');

            return redirect()->back();
        }

        $profileData = [
            'alamat' => $request->alamat,
            'niqobah' => $request->niqobah,
            'no_hp' => $request->no_hp,
            'pekerjaan' => $request->pekerjaan,
        ];

        if ($request->hasFile('foto')) {
            if ($profil->foto && File::exists(public_path('storage/'.$profil->foto))) {
                File::delete(public_path('storage/'.$profil->foto));
            }

            $imagePath = $request->file('foto')->store('image/anggota', 'public');
            $image = Image::make(storage_path('app/public/' . $imagePath));
            $image->resize(300, 400);
            $image->save();
            $profileData['foto'] = $imagePath;
        }

        $data_diri->forceFill([
            'name' => $request->nama,
            'email' => $request->email,
            'jatah_edit' => ((int) $data_diri->jatah_edit) + 1,
        ])->save();

        $profil->update($profileData);

        $request->session()->flash('sukses', 'berhasil!');

        return redirect()->back();
    }
}
