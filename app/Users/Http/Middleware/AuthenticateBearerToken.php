<?php

namespace App\Users\Http\Middleware;

use App\Users\Services\BearerTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBearerToken
{
    public function __construct(private readonly BearerTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return $this->unauthenticated();
        }

        $accessToken = $this->tokens->findValidToken($plainTextToken);

        if (! $accessToken || ! $accessToken->user->active) {
            return $this->unauthenticated();
        }

        $request->attributes->set('access_token', $accessToken);
        $request->setUserResolver(fn () => $accessToken->user);
        Auth::setUser($accessToken->user);

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }
}
