<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after Laravel's `auth` middleware on every staff route.
 *
 * `auth` answers "is this a staff account?" (every users row is staff).
 * This answers "is that account still allowed in?" — a staff member deactivated
 * mid-session is logged out on their next request instead of keeping access
 * until the session expires. It never looks at `position`: AVT and PDO are
 * treated identically.
 */
class EnsureStaffIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'This staff account is inactive.']);
        }

        return $next($request);
    }
}
