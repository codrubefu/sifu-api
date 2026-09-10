<?php

namespace App\Users\Services;

use App\Users\Models\PersonalAccessToken;
use App\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BearerTokenService
{
    public function create(User $user, string $name = 'api-token', array $abilities = ['*']): string
    {
        $plainTextToken = Str::random(80);

        $user->accessTokens()->create([
            'name' => $name,
            'token' => $this->hash($plainTextToken),
            'abilities' => $abilities,
            'expires_at' => Carbon::now()->addMinutes(max(1, (int) config('security.tokens.expiration_minutes', 60))),
        ]);

        return $plainTextToken;
    }

    public function findValidToken(string $plainTextToken): ?PersonalAccessToken
    {
        $accessToken = PersonalAccessToken::query()
            ->with('user')
            ->where('token', $this->hash($plainTextToken))
            ->first();

        if (! $accessToken || ! $accessToken->user) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $accessToken->forceFill(['last_used_at' => Carbon::now()])->save();

        return $accessToken;
    }

    public function revoke(PersonalAccessToken $accessToken): void
    {
        $accessToken->delete();
    }

    /** Revoke every API session after a credential/status change or security incident. */
    public function revokeAll(User $user): int
    {
        return $user->accessTokens()->delete();
    }

    private function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }
}
