<?php

use App\Campaigns\Http\Controllers\Api\CampaignController;
use App\Notifications\Http\Controllers\Api\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    // Campaigns expose member contact data (preview) and send mass messages: never self-service.
    Route::get('/campaigns', [CampaignController::class, 'index'])->middleware('right:campaigns.view,campaigns.manage');
    Route::post('/campaigns', [CampaignController::class, 'store'])->middleware('right:campaigns.manage');
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('right:campaigns.manage');
    Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('right:campaigns.manage');
    Route::get('/campaigns/{campaign}/preview', [CampaignController::class, 'preview'])->middleware(['right:campaigns.view,campaigns.manage', 'throttle:expensive']);
    Route::post('/campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->middleware(['right:campaigns.manage', 'throttle:expensive']);
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->middleware('right:campaigns.manage');
    Route::get('/campaigns/{campaign}/statistics', [CampaignController::class, 'statistics'])->middleware('right:campaigns.view,campaigns.manage');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'preference']);
    Route::post('/push-devices', [NotificationPreferenceController::class, 'registerDevice']);
    Route::delete('/push-devices/{device}', [NotificationPreferenceController::class, 'removeDevice']);
});
