<?php

use App\Sms\Http\Controllers\Api\SmsMessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/sms-messages', [SmsMessageController::class, 'index'])
        ->middleware('right:sms.view,services.manage');
});