<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if (! array_key_exists('password_changed_at', $user->getAttributes()) || $user->password_changed_at === null) {
            return response()->json([
                'success' => false,
                'code' => 'PASSWORD_CHANGE_REQUIRED',
                'message' => 'Anda harus mengganti password sebelum melanjutkan.',
            ], 428);
        }

        return $next($request);
    }
}
