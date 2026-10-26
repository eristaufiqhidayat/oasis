<?php

namespace App\Support\Auth;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Token Bearer HP: dibuat saat login, disimpan sebagai hash SHA-256. */
class ApiTokens
{
    public const ATTRIBUTE = 'api_token';

    /** @return array{0: ApiToken, 1: string} token tersimpan dan teks token (hanya ditampilkan sekali). */
    public static function issue(User $user, string $device): array
    {
        $plain = Str::random(64);
        $token = $user->apiTokens()->create([
            'device_name' => Str::limit($device, 100, ''),
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays((int) config('mobile.token_days', 30)),
        ]);

        return [$token, $plain];
    }

    public static function resolve(Request $request): ?User
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return null;
        }

        $token = ApiToken::with('user.employee', 'user.role')->where('token_hash', hash('sha256', $plain))->first();

        if (! $token || ($token->expires_at && $token->expires_at->isPast()) || ! $token->user?->is_active) {
            return null;
        }

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set(self::ATTRIBUTE, $token);

        return $token->user;
    }

    public static function current(Request $request): ?ApiToken
    {
        if ($token = $request->attributes->get(self::ATTRIBUTE)) {
            return $token;
        }

        $plain = $request->bearerToken();

        return $plain ? ApiToken::where('token_hash', hash('sha256', $plain))->first() : null;
    }
}
