<?php

use App\Dashboard\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'show'])
        ->middleware('right:dashboard.view,reports.view,reports.manage');
});
