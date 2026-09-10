<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Models\PersonalAccessToken;
use App\Users\Models\User;
use App\Users\Services\BearerTokenService;
use App\Users\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(
        private readonly BearerTokenService $tokens,
        private readonly OrganizationAccessService $organizationAccess,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->where('organization_id', $credentials['organization_id'])
            ->first();

        if (! $user || ! $user->active || $user->password === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->logAuthentication($request, false, $user?->id, (int) $credentials['organization_id'], $credentials['email']);
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $this->logAuthentication($request, true, $user->id, $user->organization_id, $credentials['email']);

        return response()->json([
            'token' => $this->tokens->create($user),
            'token_type' => 'Bearer',
            'user' => $this->organizationAccess->loadUserAccessRelations($user),
        ]);
    }

    private function logAuthentication(Request $request, bool $successful, ?int $userId, int $organizationId, string $declaredIdentity): void
    {
        Log::info('API authentication attempt', [
            'successful' => $successful,
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'declared_identity_hash' => hash('sha256', mb_strtolower($declaredIdentity)),
            'ip' => $request->ip(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->organizationAccess->loadUserAccessRelations($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->attributes->get('access_token');

        if ($accessToken instanceof PersonalAccessToken) {
            $this->tokens->revoke($accessToken);
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
