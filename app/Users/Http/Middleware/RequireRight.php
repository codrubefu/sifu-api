<?php

namespace App\Users\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRight
{
    public function handle(Request $request, Closure $next, string ...$rights): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($rights === [] || ! $user->hasAnyRight($rights)) {
            return $this->forbidden();
        }

        return $next($request);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'message' => 'Forbidden.',
        ], 403);
    }
}
