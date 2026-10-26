<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API HP hanya untuk akun yang terhubung ke karyawan berjabatan sales motoris dan punya depo. */
class EnsureSalesman
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isSalesman() && $user->employee->is_active && $user->employee->depot_id, 403);

        return $next($request);
    }
}
