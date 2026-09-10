<?php

namespace App\Users\Services;

use App\Users\Models\PasswordSetupToken;
use App\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PasswordSetupTokenService
{
    public function generate(User $user): string
    {
        $user->passwordSetupTokens()->whereNull('used_at')->delete();

        $plainTextToken = Str::random(64);

        $user->passwordSetupTokens()->create([
            'token' => $this->hash($plainTextToken),
            'expires_at' => Carbon::now()->addMinutes(max(1, (int) config('security.tokens.password_setup_expiration_minutes', 1440))),
        ]);

        return $plainTextToken;
    }

    public function resolve(User $user, string $plainTextToken): ?PasswordSetupToken
    {
        $token = $user->passwordSetupTokens()
            ->where('token', $this->hash($plainTextToken))
            ->whereNull('used_at')
            ->first();

        if (! $token || $token->expires_at->isPast()) {
            return null;
        }

        return $token;
    }

    public function consume(PasswordSetupToken $token): void
    {
        $token->update(['used_at' => Carbon::now()]);
    }

    public function complete(User $user, string $plainTextToken, string $newPassword): bool
    {
        $token = $this->resolve($user, $plainTextToken);

        if (! $token) {
            return false;
        }

        $user->update(['password' => $newPassword]);
        $this->consume($token);

        return true;
    }

    private function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }
}
