<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks every authenticated non-admin user from admin endpoints (BR-09).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            return ApiResponse::error('Akses ditolak. Endpoint ini hanya untuk Admin.', 403);
        }

        return $next($request);
    }
}
