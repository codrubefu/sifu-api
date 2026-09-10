<?php

use App\Events\Http\Controllers\Api\EventController;
use App\Events\Http\Controllers\Api\EventCategoryController;
use App\Events\Http\Controllers\Api\EventOccurrenceController;
use App\Events\Http\Controllers\Api\EventParticipantController;
use App\CheckIns\Http\Controllers\Api\CheckInController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/event-categories', [EventCategoryController::class, 'index'])
        ->middleware('right:events.view,events.manage');
    Route::post('/event-categories', [EventCategoryController::class, 'store'])
        ->middleware('right:events.manage');
    Route::get('/event-categories/{eventCategory}', [EventCategoryController::class, 'show'])
        ->middleware('right:events.view,events.manage');
    Route::put('/event-categories/{eventCategory}', [EventCategoryController::class, 'update'])
        ->middleware('right:events.manage');
    Route::patch('/event-categories/{eventCategory}', [EventCategoryController::class, 'update'])
        ->middleware('right:events.manage');
    Route::delete('/event-categories/{eventCategory}', [EventCategoryController::class, 'destroy'])
        ->middleware('right:events.manage');

    Route::get('/events', [EventController::class, 'index'])
        ->middleware('right:events.view,events.manage');
    Route::post('/events', [EventController::class, 'store'])
        ->middleware('right:events.manage');
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::put('/events/{event}', [EventController::class, 'update'])
        ->middleware('right:events.manage');
    Route::patch('/events/{event}', [EventController::class, 'update'])
        ->middleware('right:events.manage');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])
        ->middleware('right:events.manage');

    Route::get('/events/{event}/occurrences', [EventOccurrenceController::class, 'index']);
    Route::get('/event-occurrences', [EventOccurrenceController::class, 'all']);
    Route::get('/event-occurrences/{occurrence}', [EventOccurrenceController::class, 'show']);
    Route::get('/event-occurrences/{occurrence}/eligible-participants', [EventParticipantController::class, 'eligible'])
        ->middleware('right:event_participants.view,event_participants.manage');
    Route::get('/event-occurrences/{occurrence}/participants/download/pdf', [EventParticipantController::class, 'downloadPdf'])
        ->middleware('right:event_participants.view,event_participants.manage');

    Route::get('/event-occurrences/{occurrence}/participants', [EventParticipantController::class, 'index'])
        ->middleware('right:event_participants.view,event_participants.manage');
    Route::post('/event-occurrences/{occurrence}/participants/bulk', [EventParticipantController::class, 'bulkStore'])
        ->middleware('right:event_participants.manage');
    Route::post('/event-occurrences/{occurrence}/participants', [EventParticipantController::class, 'store'])
        ->middleware('right:event_participants.manage');
    Route::put('/event-occurrences/{occurrence}/participants/{user}', [EventParticipantController::class, 'update'])
        ->middleware('right:event_participants.manage');
    Route::patch('/event-occurrences/{occurrence}/participants/{user}', [EventParticipantController::class, 'update'])
        ->middleware('right:event_participants.manage');
    Route::delete('/event-occurrences/{occurrence}/participants/{user}', [EventParticipantController::class, 'destroy'])
        ->middleware('right:event_participants.manage');

    Route::get('/check-ins/occurrences/current', [CheckInController::class, 'currentOccurrences'])
        ->middleware('right:event_participants.manage,checkins.manage');
    Route::post('/check-ins/search', [CheckInController::class, 'search'])
        ->middleware('right:event_participants.manage,checkins.manage');
    Route::post('/check-ins/confirm', [CheckInController::class, 'confirm'])
        ->middleware('right:event_participants.manage,checkins.manage');
});
