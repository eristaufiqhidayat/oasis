<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Akun dengan must_change_password diarahkan ke halaman ganti password sebelum memakai aplikasi. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->is('ganti-password', 'logout')) {
            return redirect('/ganti-password')->with('galat', 'Ganti password sementara Anda terlebih dulu.');
        }

        return $next($request);
    }
}
