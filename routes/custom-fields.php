<?php

use App\CustomFields\Http\Controllers\Api\CustomFieldController;
use App\CustomFields\Http\Controllers\Api\CustomFieldValueController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/custom-fields', [CustomFieldController::class, 'index'])
        ->middleware('right:custom-fields.view,custom-fields.manage');
    Route::post('/custom-fields', [CustomFieldController::class, 'store'])
        ->middleware('right:custom-fields.manage');
    Route::get('/custom-fields/{customField}', [CustomFieldController::class, 'show'])
        ->middleware('right:custom-fields.view,custom-fields.manage');
    Route::put('/custom-fields/{customField}', [CustomFieldController::class, 'update'])
        ->middleware('right:custom-fields.manage');
    Route::patch('/custom-fields/{customField}', [CustomFieldController::class, 'update'])
        ->middleware('right:custom-fields.manage');
    Route::delete('/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])
        ->middleware('right:custom-fields.manage');

    Route::get('/{entityType}/{entityId}/custom-field-values', [CustomFieldValueController::class, 'show'])
        ->middleware('right:custom-fields.view,custom-fields.manage')
        ->whereNumber('entityId');
    Route::post('/{entityType}/{entityId}/custom-field-values', [CustomFieldValueController::class, 'store'])
        ->middleware('right:custom-fields.manage')
        ->whereNumber('entityId');
});
