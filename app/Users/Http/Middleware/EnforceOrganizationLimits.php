<?php

namespace App\Users\Http\Middleware;

use App\Users\Services\OrganizationSubscriptionService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceOrganizationLimits
{
    public function __construct(private readonly OrganizationSubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! in_array($request->segment(2), ['users', 'clients', 'administrators', 'groups', 'locations', 'events', 'event-occurrences'], true)) {
            return $next($request);
        }
        $ids = [$request->user()->organization_id];

        return $this->subscriptions->guard($ids, function () use ($request, $next): Response {
            $response = $next($request);
            // Laravel may render downstream exceptions into responses inside the routing pipeline.
            if ($response->getStatusCode() >= 400) {
                throw new HttpResponseException($response);
            }

            return $response;
        });
    }
}
