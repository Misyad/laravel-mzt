<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Login extends Controller
{
    function viewLogin(){
        return view('login_view');
    }

    function action_login(Request $request)
    {
        $credentials = $request->validate([
            'id_anggota' => ['required'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([
            'id_anggota' => $credentials['id_anggota'],
            'password' => $credentials['password'],
            'is_active' => '1',
        ])) {
            return redirect('/login');
        }

        $request->session()->regenerate();
        $request->session()->put('password_hash_web', Auth::user()->getAuthPassword());

        Auth::user()->forceFill([
            'last_login' => now(),
            'login_count' => ((int) Auth::user()->login_count) + 1,
        ])->save();

        return redirect()->intended('/dashboard');
    }

    function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}