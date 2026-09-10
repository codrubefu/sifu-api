<?php

namespace App\Reporting\Http\Controllers\Api;

use App\Reporting\Http\Requests\EventParticipationReportRequest;
use App\Reporting\Http\Requests\ReportFilterRequest;
use App\Reporting\Http\Requests\ServiceExpirationReportRequest;
use App\Reporting\Jobs\GenerateReportExport;
use App\Reporting\Models\ReportExport;
use App\Reporting\Services\EventParticipationReportService;
use App\Reporting\Services\FinancialDocumentReportService;
use App\Reporting\Services\FinancialReportService;
use App\Reporting\Services\ServiceExpirationReportService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function serviceExpirations(ServiceExpirationReportRequest $request, ServiceExpirationReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->report($request->user()->organization_id, $request->validated())]);
    }

    public function exportServiceExpirations(ServiceExpirationReportRequest $request, ServiceExpirationReportService $reports): StreamedResponse
    {
        $rows = $reports->rows($request->user()->organization_id, $request->validated());
        $headers = ['assignment_id', 'user_id', 'member_name', 'phone', 'service_id', 'service_name', 'service_type', 'status', 'start_date', 'expires_at', 'days_until_expiration', 'last_notification_at', 'category'];

        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, array_values($row));
            }
            fclose($stream);
        }, 'service-expirations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function eventParticipation(EventParticipationReportRequest $request, EventParticipationReportService $reports): JsonResponse
    {
        $filters = $request->validated();
        $this->assertTenant($request, $filters);

        return response()->json(['data' => $reports->aggregate($request->user()->organization_id, $filters)]);
    }

    public function aggregate(ReportFilterRequest $request, FinancialReportService $reports): JsonResponse
    {
        $this->assertTenant($request, $request->validated());
        return response()->json(['data' => $reports->aggregate($request->user()->organization_id, $request->validated())]);
    }

    public function export(ReportFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $this->assertTenant($request, $filters);
        $format = validator($request->all(), ['format' => ['required', Rule::in(['csv', 'xlsx'])]])->validate()['format'];
        $export = ReportExport::query()->create([
            'id' => (string) Str::uuid(), 'organization_id' => $request->user()->organization_id,
            'requested_by' => $request->user()->id, 'format' => $format, 'filters' => $filters, 'status' => 'pending',
        ]);
        GenerateReportExport::dispatch($export->id);
        return response()->json(['data' => $export], 202);
    }

    public function show(Request $request, string $export): JsonResponse
    {
        return response()->json(['data' => $this->tenantExport($request, $export)]);
    }

    public function download(Request $request, string $export): StreamedResponse
    {
        $record = $this->tenantExport($request, $export);
        abort_unless($record->status === 'completed' && $record->path, 409, 'Export is not ready.');
        return Storage::disk('local')->download($record->path, "financial-report.{$record->format}");
    }

    public function financialDocuments(ReportFilterRequest $request, FinancialDocumentReportService $documents): JsonResponse
    {
        $filters = $request->validated();
        $this->assertTenant($request, $filters);

        return response()->json(['data' => $documents->rows($request->user()->organization_id, $filters)]);
    }

    public function receivables(ReportFilterRequest $request, FinancialReportService $reports): JsonResponse
    {
        $filters = $request->validated();
        $this->assertTenant($request, $filters);

        return response()->json(['data' => $reports->receivableRows($request->user()->organization_id, $filters)]);
    }

    public function downloadFinancialDocument(
        Request $request,
        FinancialDocumentReportService $documents,
        string $type,
        int $id,
        string $format = 'pdf',
    ): Response {
        abort_unless(in_array($format, ['pdf', 'xml'], true), 404);

        return $documents->download($request->user()->organization_id, $type, $id, $format);
    }

    public function downloadFinancialDocuments(ReportFilterRequest $request, FinancialDocumentReportService $documents): StreamedResponse
    {
        $filters = $request->validated();
        $this->assertTenant($request, $filters);

        return $documents->downloadZip($request->user()->organization_id, $filters);
    }

    private function tenantExport(Request $request, string $id): ReportExport
    {
        return ReportExport::query()->where('organization_id', $request->user()->organization_id)->findOrFail($id);
    }

    private function assertTenant(Request $request, array $filters): void
    {
        abort_if(isset($filters['organization_id']) && (int) $filters['organization_id'] !== (int) $request->user()->organization_id, 403, 'Cross-organization reports are forbidden.');
    }
}
