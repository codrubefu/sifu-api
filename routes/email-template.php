<?php

use App\Notifications\Http\Controllers\Api\EmailTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('email-templates/types', [EmailTemplateController::class, 'types'])->middleware('right:email_templates.view,email_templates.manage');
    Route::apiResource('email-templates', EmailTemplateController::class)->only(['index', 'show'])->middleware('right:email_templates.view,email_templates.manage');
    Route::apiResource('email-templates', EmailTemplateController::class)->only(['store', 'update', 'destroy'])->middleware('right:email_templates.manage');
});
