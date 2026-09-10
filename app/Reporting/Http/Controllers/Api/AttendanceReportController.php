<?php

namespace App\Reporting\Http\Controllers\Api;

use App\Reporting\Http\Requests\AttendanceReportRequest;
use App\Reporting\Services\AttendanceReportService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AttendanceReportController extends Controller
{
    public function show(AttendanceReportRequest $request, AttendanceReportService $reports): JsonResponse
    {
        return response()->json([
            'data' => $reports->aggregate($request->user()->organization_id, $request->validated()),
        ]);
    }

    public function export(AttendanceReportRequest $request, AttendanceReportService $reports): Response
    {
        $filters = $request->validated();
        $format = $filters['format'] ?? 'csv';
        unset($filters['format']);
        $contentType = $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response($reports->export($request->user()->organization_id, $filters, $format), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=attendance-report.{$format}",
        ]);
    }
}
