<?php

namespace App\Reporting\Services;

use App\Payments\Models\Payment;
use App\Payments\Services\ReceiptService;
use App\Service\Models\ServiceUser;
use App\Service\Services\PaymentNoteService;
use App\Service\Services\ServiceInvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class FinancialDocumentReportService
{
    public function __construct(
        private readonly ServiceInvoiceService $invoices,
        private readonly PaymentNoteService $paymentNotes,
        private readonly ReceiptService $receipts,
    ) {}

    public function rows(int $organizationId, array $filters): array
    {
        return collect()
            ->merge($this->assignmentRows($organizationId, $filters, 'invoice'))
            ->merge($this->assignmentRows($organizationId, $filters, 'payment_note'))
            ->merge($this->receiptRows($organizationId, $filters))
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function download(int $organizationId, string $type, int $id, string $format = 'pdf'): Response
    {
        if ($type === 'receipt') {
            $payment = Payment::query()
                ->where('organization_id', $organizationId)
                ->where('status', Payment::STATUS_CONFIRMED)
                ->whereNotNull('receipt_number')
                ->findOrFail($id);

            return $this->receipts->download($payment);
        }

        $assignment = $this->assignment($organizationId, $id);
        if ($type === 'invoice') {
            abort_if(blank($assignment->invoice_number), 404);
            return $format === 'xml'
                ? $this->invoices->xmlDownload($assignment)
                : $this->invoices->download($assignment);
        }
        if ($type === 'payment_note') {
            abort_if(blank($assignment->bill_number), 404);
            return $this->paymentNotes->download($assignment);
        }

        abort(404);
    }

    public function downloadZip(int $organizationId, array $filters): StreamedResponse
    {
        $rows = $this->rows($organizationId, $filters);
        abort_if($rows === [], 404, 'No documents found for the selected period.');

        $temporary = tempnam(sys_get_temp_dir(), 'financial-documents-');
        $zip = new ZipArchive();
        $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($rows as $row) {
            $zip->addFromString($row['filename'], $this->content($organizationId, $row['type'], (int) $row['id']));
            if ($row['type'] === 'invoice' && isset($row['xml_filename'])) {
                $zip->addFromString($row['xml_filename'], $this->content($organizationId, $row['type'], (int) $row['id'], 'xml'));
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($temporary): void {
            readfile($temporary);
            @unlink($temporary);
        }, 'documente-financiare.zip', ['Content-Type' => 'application/zip']);
    }

    private function content(int $organizationId, string $type, int $id, string $format = 'pdf'): string
    {
        if ($type === 'receipt') {
            return $this->receipts->pdf(Payment::query()->where('organization_id', $organizationId)->findOrFail($id));
        }

        $assignment = $this->assignment($organizationId, $id);
        if ($type === 'invoice' && $format === 'xml') {
            return $this->invoices->xml($assignment);
        }

        return $type === 'invoice'
            ? $this->invoices->pdf($assignment)
            : $this->paymentNotes->pdf($assignment);
    }

    private function assignmentRows(int $organizationId, array $filters, string $type): array
    {
        $numberColumn = $type === 'invoice' ? 'su.invoice_number' : 'su.bill_number';
        $label = $type === 'invoice' ? 'Factura' : 'Nota de plata';

        $query = DB::table('service_user as su')
            ->join('services as s', 's.id', '=', 'su.service_id')
            ->join('users as u', 'u.id', '=', 'su.user_id')
            ->where('s.organization_id', $organizationId)
            ->whereNotNull($numberColumn)
            ->select([
                'su.id',
                DB::raw($numberColumn.' as number'),
                'su.created_at',
                's.name as service_name',
                's.price as amount',
                's.currency',
                'u.first_name',
                'u.last_name',
                'u.email',
            ]);

        $this->applyDateFilters($query, 'su.created_at', $filters);

        return $query->get()->map(function ($row) use ($type, $label): array {
            $name = trim(($row->last_name ?? '').' '.($row->first_name ?? '')) ?: $row->email;
            return [
                'id' => (int) $row->id,
                'type' => $type,
                'type_label' => $label,
                'number' => $row->number,
                'date' => Carbon::parse($row->created_at)->toDateString(),
                'member' => $name,
                'description' => $row->service_name,
                'amount' => (float) $row->amount,
                'currency' => $row->currency,
                'filename' => ($type === 'invoice' ? 'factura-' : 'nota-plata-').$row->number.'.pdf',
                'xml_filename' => $type === 'invoice' ? 'efactura-'.$row->number.'.xml' : null,
            ];
        })->all();
    }

    private function receiptRows(int $organizationId, array $filters): array
    {
        $query = Payment::query()
            ->where('organization_id', $organizationId)
            ->where('status', Payment::STATUS_CONFIRMED)
            ->whereNotNull('receipt_number');

        $this->applyDateFilters($query, 'paid_at', $filters);

        return $query->get()->map(function (Payment $payment): array {
            return [
                'id' => $payment->id,
                'type' => 'receipt',
                'type_label' => 'Chitanta',
                'number' => $payment->receipt_number,
                'date' => ($payment->paid_at ?? $payment->created_at)->toDateString(),
                'member' => trim($payment->last_name.' '.$payment->first_name),
                'description' => 'Plata #'.$payment->id,
                'amount' => (float) $payment->amount,
                'currency' => null,
                'filename' => $this->receipts->filename($payment),
            ];
        })->all();
    }

    private function assignment(int $organizationId, int $id): ServiceUser
    {
        return ServiceUser::query()
            ->whereKey($id)
            ->whereHas('service', fn ($query) => $query->where('organization_id', $organizationId))
            ->firstOrFail();
    }

    private function applyDateFilters(mixed $query, string $column, array $filters): void
    {
        if (isset($filters['from'])) {
            $query->where($column, '>=', $filters['from'].' 00:00:00');
        }
        if (isset($filters['to'])) {
            $query->where($column, '<=', $filters['to'].' 23:59:59');
        }
    }
}
