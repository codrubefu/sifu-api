<?php

use App\Users\Http\Controllers\Api\AuthController;
use App\Users\Http\Controllers\Api\GroupController;
use App\Users\Http\Controllers\Api\GdprController;
use App\Users\Http\Controllers\Api\GradeController;
use App\Users\Http\Controllers\Api\LocationController;
use App\Users\Http\Controllers\Api\LocationGroupController;
use App\Users\Http\Controllers\Api\MeController;
use App\Users\Http\Controllers\Api\OrganizationController;
use App\Users\Http\Controllers\Api\PasswordResetController;
use App\Users\Http\Controllers\Api\RightController;
use App\Users\Http\Controllers\Api\SmtpSettingController;
use App\Users\Http\Controllers\Api\UserController;
use App\Users\Http\Controllers\Api\UserDocumentController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:login');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:login');
Route::get('/organizations/slug/{slug}', [OrganizationController::class, 'showBySlug']);

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me/password', [MeController::class, 'updatePassword']);
    Route::get('/me/custom-fields', [MeController::class, 'customFields']);
    Route::get('/me/events', [MeController::class, 'events']);
    Route::get('/me/services', [MeController::class, 'services']);
    Route::get('/me/children', [MeController::class, 'children']);
    Route::get('/me/grades', [MeController::class, 'grades']);
    Route::get('/me/documents', [MeController::class, 'documents']);
    Route::get('/me/documents/{document}/download', [MeController::class, 'downloadDocument']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me/privacy/data', [GdprController::class, 'access']);
    Route::post('/me/privacy/exports', [GdprController::class, 'export']);
    Route::patch('/me/privacy/rectification', [GdprController::class, 'rectify']);
    Route::post('/me/privacy/consents', [GdprController::class, 'consent']);
    Route::post('/me/privacy/erasure-requests', [GdprController::class, 'requestErasure']);
    Route::get('/privacy/exports/{export}', [GdprController::class, 'exportStatus']);
    Route::get('/privacy/exports/{export}/download', [GdprController::class, 'download'])->name('gdpr.exports.download');

    Route::get('/users/{user}/privacy/data', [GdprController::class, 'access'])->middleware('right:gdpr.export');
    Route::post('/users/{user}/privacy/exports', [GdprController::class, 'export'])->middleware('right:gdpr.export');
    Route::patch('/users/{user}/privacy/rectification', [GdprController::class, 'rectify'])->middleware('right:gdpr.process');
    Route::post('/users/{user}/privacy/consents', [GdprController::class, 'consent'])->middleware('right:gdpr.process');
    Route::post('/users/{user}/privacy/erasure-requests', [GdprController::class, 'requestErasure'])->middleware('right:gdpr.process');
    Route::post('/privacy/requests/{gdprRequest}/process', [GdprController::class, 'process'])->middleware('right:gdpr.process');

    Route::get('/rights', [RightController::class, 'index'])->middleware('right:rights.view');
    Route::post('/rights', [RightController::class, 'store'])->middleware('right:rights.manage');
    Route::get('/rights/{right}', [RightController::class, 'show'])->middleware('right:rights.view');
    Route::put('/rights/{right}', [RightController::class, 'update'])->middleware('right:rights.manage');
    Route::patch('/rights/{right}', [RightController::class, 'update'])->middleware('right:rights.manage');
    Route::delete('/rights/{right}', [RightController::class, 'destroy'])->middleware('right:rights.manage');

    Route::get('/groups', [GroupController::class, 'index'])->middleware('right:groups.view');
    Route::post('/groups', [GroupController::class, 'store'])->middleware('right:groups.manage');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->middleware('right:groups.view');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->middleware('right:groups.manage');
    Route::patch('/groups/{group}', [GroupController::class, 'update'])->middleware('right:groups.manage');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->middleware('right:groups.manage');

    Route::get('/location-groups', [LocationGroupController::class, 'index'])->middleware('right:location_groups.view');
    Route::post('/location-groups', [LocationGroupController::class, 'store'])->middleware('right:location_groups.manage');
    Route::get('/location-groups/{locationGroup}', [LocationGroupController::class, 'show'])->middleware('right:location_groups.view');
    Route::put('/location-groups/{locationGroup}', [LocationGroupController::class, 'update'])->middleware('right:location_groups.manage');
    Route::patch('/location-groups/{locationGroup}', [LocationGroupController::class, 'update'])->middleware('right:location_groups.manage');
    Route::delete('/location-groups/{locationGroup}', [LocationGroupController::class, 'destroy'])->middleware('right:location_groups.manage');

    Route::get('/locations', [LocationController::class, 'index'])->middleware('right:locations.view');
    Route::post('/locations', [LocationController::class, 'store'])->middleware('right:locations.manage');
    Route::get('/locations/{location}', [LocationController::class, 'show'])->middleware('right:locations.view');
    Route::put('/locations/{location}', [LocationController::class, 'update'])->middleware('right:locations.manage');
    Route::patch('/locations/{location}', [LocationController::class, 'update'])->middleware('right:locations.manage');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->middleware('right:locations.manage');

    Route::get('/smtp-settings', [SmtpSettingController::class, 'show'])->middleware('right:smtp_settings.view');
    Route::post('/smtp-settings', [SmtpSettingController::class, 'store'])->middleware('right:smtp_settings.manage');
    Route::put('/smtp-settings', [SmtpSettingController::class, 'update'])->middleware('right:smtp_settings.manage');
    Route::patch('/smtp-settings', [SmtpSettingController::class, 'update'])->middleware('right:smtp_settings.manage');
    Route::delete('/smtp-settings', [SmtpSettingController::class, 'destroy'])->middleware('right:smtp_settings.manage');

    Route::get('/grades', [GradeController::class, 'index'])->middleware('right:grades.view');
    Route::post('/grades', [GradeController::class, 'store'])->middleware('right:grades.manage');
    Route::get('/grades/{grade}/users', [GradeController::class, 'users'])->middleware('right:grades.view');
    Route::get('/grades/{grade}', [GradeController::class, 'show'])->middleware('right:grades.view');
    Route::put('/grades/{grade}', [GradeController::class, 'update'])->middleware('right:grades.manage');
    Route::patch('/grades/{grade}', [GradeController::class, 'update'])->middleware('right:grades.manage');
    Route::delete('/grades/{grade}', [GradeController::class, 'destroy'])->middleware('right:grades.manage');

    Route::get('/administrators', [UserController::class, 'administrators'])->middleware('right:users.view');
    Route::get('/clients', [UserController::class, 'clients'])->middleware('right:users.view');
    Route::get('/users', [UserController::class, 'index'])->middleware('right:users.view');
    Route::get('/users/search/user-code', [UserController::class, 'searchByUserCode'])->middleware('right:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::patch('/users/service/{user}', [UserController::class, 'syncServices'])->middleware('right:users.manage');
    Route::get('/users/{user}/activity', [UserController::class, 'activity'])->middleware('right:users.view');
    Route::get('/users/{user}/events', [UserController::class, 'events'])->middleware('right:users.view');
    Route::get('/users/{user}/grades', [GradeController::class, 'userIndex'])->middleware('right:grades.view');
    Route::post('/users/{user}/grades', [GradeController::class, 'userStore'])->middleware('right:grades.manage');
    Route::get('/users/{user}/grades/{userGrade}', [GradeController::class, 'userShow'])->middleware('right:grades.view');
    Route::put('/users/{user}/grades/{userGrade}', [GradeController::class, 'userUpdate'])->middleware('right:grades.manage');
    Route::patch('/users/{user}/grades/{userGrade}', [GradeController::class, 'userUpdate'])->middleware('right:grades.manage');
    Route::delete('/users/{user}/grades/{userGrade}', [GradeController::class, 'userDestroy'])->middleware('right:grades.manage');
    Route::get('/users/{user}/documents', [UserDocumentController::class, 'index'])->middleware('right:user-documents.view');
    Route::post('/users/{user}/documents', [UserDocumentController::class, 'store'])->middleware('right:user-documents.upload');
    Route::post('/users/{user}/documents/{document}/replace', [UserDocumentController::class, 'replace'])->middleware('right:user-documents.upload');
    Route::post('/users/{user}/documents/{document}/download-url', [UserDocumentController::class, 'signedDownloadUrl'])->middleware('right:user-documents.view');
    Route::get('/users/{user}/documents/{document}/download', [UserDocumentController::class, 'download'])
        ->middleware('right:user-documents.view')
        ->name('user-documents.download');
    Route::delete('/users/{user}/documents/{document}', [UserDocumentController::class, 'destroy'])->middleware('right:user-documents.delete');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');

    Route::post('/clients', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::get('/clients/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/clients/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/clients/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/clients/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');

    Route::post('/administrators', [UserController::class, 'store'])->middleware('right:users.manage');
    Route::get('/administrators/{user}', [UserController::class, 'show'])->middleware('right:users.view');
    Route::put('/administrators/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::patch('/administrators/{user}', [UserController::class, 'update'])->middleware('right:users.manage');
    Route::delete('/administrators/{user}', [UserController::class, 'destroy'])->middleware('right:users.manage');
});
