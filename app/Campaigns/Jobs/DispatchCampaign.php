<?php

namespace App\Campaigns\Jobs;

use App\Campaigns\Models\Campaign;
use App\Campaigns\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchCampaign implements ShouldQueue
{
    use Queueable;
    public function __construct(public int $campaignId) {}
    public function handle(CampaignService $service): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign) $service->dispatch($campaign);
    }
}
