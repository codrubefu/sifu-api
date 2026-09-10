<?php

use App\Reporting\Http\Controllers\Api\ReportController;
use App\Reporting\Http\Controllers\Api\AttendanceReportController;
use App\Reporting\Http\Controllers\Api\SegmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.bearer')->group(function (): void {
    Route::get('/reports/attendance', [AttendanceReportController::class, 'show'])->middleware(['right:reports.view', 'throttle:expensive']);
    Route::get('/reports/attendance/export', [AttendanceReportController::class, 'export'])->middleware(['right:reports.export', 'throttle:expensive']);
    Route::get('/reports/financial', [ReportController::class, 'aggregate'])->middleware(['right:reports.view', 'throttle:expensive']);
    Route::get('/reports/service-expirations', [ReportController::class, 'serviceExpirations'])->middleware(['right:reports.view', 'throttle:expensive']);
    Route::get('/reports/service-expirations/export', [ReportController::class, 'exportServiceExpirations'])->middleware(['right:reports.export', 'throttle:expensive']);
    Route::get('/reports/financial/receivables', [ReportController::class, 'receivables'])->middleware(['right:reports.view', 'throttle:expensive']);
    Route::get('/reports/event-participation', [ReportController::class, 'eventParticipation'])->middleware(['right:reports.view', 'throttle:expensive']);
    Route::get('/reports/financial-documents', [ReportController::class, 'financialDocuments'])->middleware(['right:reports.view', 'throttle:expensive']);
    Route::get('/reports/financial-documents/download', [ReportController::class, 'downloadFinancialDocuments'])->middleware(['right:reports.export', 'throttle:expensive']);
    Route::get('/reports/financial-documents/{type}/{id}/download/{format?}', [ReportController::class, 'downloadFinancialDocument'])
        ->whereIn('format', ['pdf', 'xml'])
        ->middleware('right:reports.export');
    Route::post('/reports/financial/exports', [ReportController::class, 'export'])->middleware(['right:reports.export', 'throttle:expensive']);
    Route::get('/reports/exports/{export}', [ReportController::class, 'show'])->middleware('right:reports.export');
    Route::get('/reports/exports/{export}/download', [ReportController::class, 'download'])->middleware('right:reports.export');

    Route::get('/segments', [SegmentController::class, 'index'])->middleware('right:segments.view,segments.manage');
    Route::post('/segments', [SegmentController::class, 'store'])->middleware('right:segments.manage');
    Route::put('/segments/{segment}', [SegmentController::class, 'update'])->middleware('right:segments.manage');
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->middleware('right:segments.manage');
    Route::get('/segments/{segment}/members', [SegmentController::class, 'members'])->middleware(['right:segments.view,segments.manage', 'throttle:expensive']);
});
