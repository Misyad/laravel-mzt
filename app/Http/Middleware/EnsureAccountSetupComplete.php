<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountSetupComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ((bool) ($user->account_setup_required ?? false)) {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_SETUP_REQUIRED',
                'message' => 'Selesaikan pengaturan akun sebelum melanjutkan.',
            ], 428);
        }

        return $next($request);
    }
}
