<?php

use App\Campaigns\Jobs\DispatchCampaign;
use App\Campaigns\Models\Campaign;
use App\Notifications\Jobs\DispatchServiceLifecycleNotifications;
use App\Articles\Jobs\TransitionArticlePublicationStatus;
use App\Service\Jobs\SendExpiringServiceSms;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Campaign::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())
        ->eachById(fn (Campaign $campaign) => DispatchCampaign::dispatch($campaign->id));
})->everyMinute()->description('campaigns.dispatch-due')->withoutOverlapping();

Schedule::job(new DispatchServiceLifecycleNotifications)
    ->name('services.lifecycle-notifications')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::job(new SendExpiringServiceSms)->daily();
Schedule::job(new TransitionArticlePublicationStatus)
    ->name('articles.transition-publication-status')
    ->everyMinute()
    ->withoutOverlapping();
