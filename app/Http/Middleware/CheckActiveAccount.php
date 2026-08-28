<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * C-01 — per-request active-account enforcement.
 *
 * R3 made authentication session/PAT-based, but `is_active` was only checked
 * at login. A deactivated account could therefore keep using an already-
 * issued PAT/session until natural expiry. This middleware runs AFTER
 * auth:sanctum on every protected API route and closes that gap:
 *
 *   - unauthenticated          -> 401 (defensive; normally caught by auth)
 *   - authenticated + inactive -> 401 (stateful web session is also destroyed)
 *   - authenticated + active   -> request continues
 *
 * The condition mirrors ApiController::login exactly (`is_active != '1'`).
 */
class CheckActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Always consult CURRENT database state so an account flipped inactive
        // mid-session is blocked on its very next request. In production every
        // request loads the user fresh from the DB, so the attribute is already
        // populated (no extra query). Only fall back to fresh() when the loaded
        // model is an in-memory fixture whose attribute is unset. The condition
        // mirrors ApiController::login (`is_active != '1'`).
        $isActive = $user->is_active;
        if ($isActive === null) {
            $isActive = $user->fresh()?->is_active;
        }

        if ($isActive != '1') {
            // Destroy the browser session for stateful callers so the next
            // navigation is clean. PAT holders are blocked by this check on
            // every request; their tokens are additionally revoked at the
            // moment an admin deactivates the account (setAccountStatus).
            if (Auth::guard('web')->check() && $request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['success' => false, 'message' => 'Akun tidak aktif.'], 401);
        }

        return $next($request);
    }
}
