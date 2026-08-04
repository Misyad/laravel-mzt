<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HakAksesRole;
use Illuminate\Support\Facades\View;
use App\Models\DataUser;

class Chackrole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        if (Auth::check()) {

            // untuk images
            $image = DataUser::where('id_users',  Auth::user()->id)->first();

            // untuk master tempalte
            $data_akses = HakAksesRole::where(['id_users' => Auth::user()->id])->get();
            $akses_array = [];
            foreach( $data_akses as $val){
                array_push($akses_array, $val->nama_role);
            }
            // View::share('status_akses', $akses_array);
            View::share(['status_akses' => $akses_array, 'foto_profil'=> isset($image->foto)?$image->foto:'']);

            // untuk route
            $roles = array_slice(func_get_args(), 2);
            foreach ($roles as $role) {
                
                if (in_array($role, $akses_array)) {
                    return $next($request);
                }elseif(Auth::check()){
                    return redirect()->back();
                }
            }
        }
        return redirect('/');
    }
}
