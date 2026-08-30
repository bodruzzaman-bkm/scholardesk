<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a suspension into an immediate loss of access (requirement 22).
 *
 * Checking only at the login gate would mean a suspended user keeps working
 * until their session expires — which, with a remembered login, could be
 * weeks. An administrator acting on a report expects the account to stop
 * being able to do things now, not eventually.
 *
 * Runs on every authenticated request, so the cost is one already-loaded
 * attribute read.
 */
class EnsureUserIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // A JSON caller gets a status it can act on rather than a
            // redirect to a login page it cannot render.
            if ($request->expectsJson()) {
                abort(403, trans('auth.suspended'));
            }

            return redirect()->route('login')->withErrors([
                'email' => trans('auth.suspended'),
            ]);
        }

        return $next($request);
    }
}
