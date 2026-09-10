<?php

namespace App\Campaigns\Http\Controllers\Api;

use App\Campaigns\Http\Requests\SaveCampaignRequest;
use App\Campaigns\Models\Campaign;
use App\Campaigns\Services\CampaignService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => Campaign::query()->where('organization_id', $request->user()->organization_id)->latest()->get()]);
    }
    public function store(SaveCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::query()->create($request->validated() + ['organization_id' => $request->user()->organization_id, 'created_by' => $request->user()->id]);
        return response()->json(['data' => $campaign], 201);
    }
    public function update(SaveCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $this->owned($request, $campaign); abort_unless($campaign->status === 'draft', 409);
        $campaign->update($request->validated()); return response()->json(['data' => $campaign]);
    }
    public function preview(Request $request, Campaign $campaign, CampaignService $service): JsonResponse
    {
        $this->owned($request, $campaign);
        $recipients = $service->recipients($campaign);
        return response()->json(['count' => (clone $recipients)->count(), 'data' => $recipients->limit(100)->get(['id', 'first_name', 'last_name', 'email', 'phone'])]);
    }
    public function schedule(Request $request, Campaign $campaign, CampaignService $service): JsonResponse
    {
        $this->owned($request, $campaign); $request->validate(['scheduled_at' => ['required', 'date']]);
        return response()->json(['data' => $service->schedule($campaign, $request->date('scheduled_at'))]);
    }
    public function cancel(Request $request, Campaign $campaign, CampaignService $service): JsonResponse
    {
        $this->owned($request, $campaign); return response()->json(['data' => $service->cancel($campaign)]);
    }
    public function statistics(Request $request, Campaign $campaign, CampaignService $service): JsonResponse
    {
        $this->owned($request, $campaign); return response()->json(['data' => $service->statistics($campaign)]);
    }
    private function owned(Request $request, Campaign $campaign): void
    {
        abort_unless((int) $campaign->organization_id === (int) $request->user()->organization_id, 404);
    }
}
