<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Navigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['username' => 'Username atau password salah, atau akun nonaktif.']);
        }

        $user = $request->user();

        // Akun motoris (peran tanpa izin web) hanya memakai aplikasi HP.
        if (! $user->role?->permissions) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['username' => 'Akun ini hanya untuk aplikasi HP motoris.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('login', "{$user->username} masuk");

        return redirect()->intended($user->can('dashboard.view') ? '/home' : '/'.($this->firstPage($request) ?? 'ganti-password'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function firstPage(Request $request): ?string
    {
        return Navigation::flat($request->user())[0]['slug'] ?? null;
    }
}
