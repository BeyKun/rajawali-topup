<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts sensitive admin-management routes to Super Admin users only.
 *
 * Creating admin kabupaten accounts and mapping Telkomsel zones to regions are
 * privilege-granting operations, so operators and kabupaten admins must never
 * reach them.
 */
class EnsureUserIsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== UserRole::SuperAdmin) {
            abort(403, 'Hanya Super Admin yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
