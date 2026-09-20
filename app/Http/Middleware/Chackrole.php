<?php

namespace App\Http\Middleware;

use App\Models\DataUser;
use App\Support\RoleGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class Chackrole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        $isActive = $user?->is_active;

        if ($user && $isActive === null) {
            $isActive = $user->fresh()?->is_active;
        }

        if (! $user || (string) $isActive !== '1') {
            if ($user) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect('/');
        }

        if (! array_key_exists('password_changed_at', $user->getAttributes()) || $user->password_changed_at === null) {
            return response('Anda harus mengganti password sebelum melanjutkan.', 428);
        }

        $image = DataUser::where('id_users', $user->id)->first();
        $effectiveRoles = RoleGuard::roles($user);

        View::share([
            'status_akses' => $effectiveRoles,
            'foto_profil' => $image?->foto ?: '',
        ]);

        $allowedRoles = array_map([RoleGuard::class, 'normalize'], $roles);
        if (array_intersect($effectiveRoles, $allowedRoles) !== []) {
            return $next($request);
        }

        return redirect()->back();
    }
}
