<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Auth/ChangePassword', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [], ['current_password' => 'password lama', 'password' => 'password baru']);

        $request->user()->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return redirect('/home')->with('sukses', 'Password berhasil diganti.');
    }
}
