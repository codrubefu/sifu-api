<?php

use App\Service\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/services', [ServiceController::class, 'index'])
        ->middleware('right:services.view,services.manage');
    Route::post('/services', [ServiceController::class, 'store'])
        ->middleware('right:services.create,services.manage');
    Route::get('/services/{service}', [ServiceController::class, 'show'])
        ->middleware('right:services.view,services.manage');
    Route::put('/services/{service}', [ServiceController::class, 'update'])
        ->middleware('right:services.update,services.manage');
    Route::patch('/services/{service}', [ServiceController::class, 'update'])
        ->middleware('right:services.update,services.manage');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])
        ->middleware('right:services.delete,services.manage');
    Route::post('/services/{service}/restore', [ServiceController::class, 'restore'])
        ->middleware('right:services.restore,services.manage');
    Route::patch('/services/{service}/toggle-active', [ServiceController::class, 'toggleActive'])
        ->middleware('right:services.update,services.manage');
    Route::post('/service-assignments/{assignment}/activate', [ServiceController::class, 'activate'])
        ->middleware('right:services.update,services.manage');
    Route::post('/service-assignments/{assignment}/suspend', [ServiceController::class, 'suspend'])
        ->middleware('right:services.update,services.manage');
    Route::post('/service-assignments/{assignment}/resume', [ServiceController::class, 'resume'])
        ->middleware('right:services.update,services.manage');
    Route::post('/service-assignments/{assignment}/consume', [ServiceController::class, 'consume'])
        ->middleware('right:services.update,services.manage');
    Route::get('/service-assignments/{assignment}/payment-note', [ServiceController::class, 'paymentNote'])
        ->middleware('right:services.view,services.manage');
    Route::post('/service-assignments/{assignment}/invoice', [ServiceController::class, 'generateInvoice'])
        ->middleware('right:services.update,services.manage');
    Route::get('/service-assignments/{assignment}/invoice/{format?}', [ServiceController::class, 'invoice'])
        ->whereIn('format', ['pdf', 'xml'])
        ->middleware('right:services.view,services.manage');
});
