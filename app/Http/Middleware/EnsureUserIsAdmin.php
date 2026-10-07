<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the admin dashboard to Super Admin and Operator users only.
 *
 * Authenticated customers (mobile app users) are rejected with a 403 so they
 * can never reach the distributor area, while guests still get the standard
 * authentication redirect handled by the `auth` middleware.
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowedRoles = [UserRole::SuperAdmin, UserRole::Operator, UserRole::KabupatenAdmin];

        if ($user === null || ! in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman admin.');
        }

        return $next($request);
    }
}
