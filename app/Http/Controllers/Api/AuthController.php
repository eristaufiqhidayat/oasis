<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Auth\ApiTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::with('employee')->where('username', $data['username'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => ['code' => 'invalid_credentials', 'message' => 'Username atau password salah.']], 401);
        }

        if (! $user->isSalesman() || ! $user->employee->is_active || ! $user->employee->depot_id) {
            return response()->json(['error' => ['code' => 'not_salesman', 'message' => 'Akun ini tidak terhubung ke sales motoris aktif.']], 403);
        }

        [$token, $plain] = ApiTokens::issue($user, $data['device_name']);
        $user->forceFill(['last_login_at' => now()])->save();
        auth()->setUser($user);
        ActivityLog::record('api.login', "{$user->username} login HP ({$token->device_name})");

        return $this->ok([
            'token' => $plain,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'must_change_password' => $user->must_change_password,
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        ApiTokens::current($request)?->delete();

        return $this->ok(['logged_out' => true]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['error' => ['code' => 'invalid_password', 'message' => 'Password lama salah.']], 422);
        }

        $request->user()->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return $this->ok(['changed' => true]);
    }
}
