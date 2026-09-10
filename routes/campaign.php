<?php

use App\Campaigns\Http\Controllers\Api\CampaignController;
use App\Notifications\Http\Controllers\Api\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::apiResource('campaigns', CampaignController::class)->only(['index', 'store', 'update']);
    Route::get('/campaigns/{campaign}/preview', [CampaignController::class, 'preview']);
    Route::post('/campaigns/{campaign}/schedule', [CampaignController::class, 'schedule']);
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel']);
    Route::get('/campaigns/{campaign}/statistics', [CampaignController::class, 'statistics']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'preference']);
    Route::post('/push-devices', [NotificationPreferenceController::class, 'registerDevice']);
    Route::delete('/push-devices/{device}', [NotificationPreferenceController::class, 'removeDevice']);
});
