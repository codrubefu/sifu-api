<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\CheckOrganizationLimitRequest;
use App\Users\Http\Requests\ShowOrganizationSubscriptionRequest;
use App\Users\Http\Resources\OrganizationSubscriptionResource;
use App\Users\Services\OrganizationSubscriptionService;

class OrganizationSubscriptionController extends Controller
{
    public function __construct(private readonly OrganizationSubscriptionService $subscriptions) {}

    public function show(ShowOrganizationSubscriptionRequest $request): OrganizationSubscriptionResource
    {
        return new OrganizationSubscriptionResource($this->subscriptions->show($request->user()->organization_id, $request->validated('month', now()->format('Y-m'))));
    }

    public function check(CheckOrganizationLimitRequest $request): OrganizationSubscriptionResource
    {
        $data = $request->validated();

        return new OrganizationSubscriptionResource($this->subscriptions->check($request->user()->organization_id, $data['resource'], (int) $data['quantity'], $data['month'] ?? null));
    }
}
