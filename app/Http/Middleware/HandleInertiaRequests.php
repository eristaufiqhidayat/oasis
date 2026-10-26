<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'menu' => fn () => $user ? Navigation::for($user) : [],
            'searchMenu' => fn () => $user ? Navigation::flat($user) : [],
            'currentSlug' => trim($request->path(), '/'),
            'flash' => fn () => [
                'sukses' => $request->session()->get('sukses'),
                'galat' => $request->session()->get('galat'),
            ],
            'auth' => [
                'user' => fn () => $user ? [
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role?->name,
                    'permissions' => $user->role?->permissions ?? [],
                ] : null,
            ],
        ];
    }
}
