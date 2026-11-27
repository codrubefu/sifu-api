<?php

namespace App\Reporting\Services;

use App\Events\Models\EventParticipant;
use App\Events\Services\EventParticipantInvoiceService;
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
        private readonly EventParticipantInvoiceService $eventInvoices,
    ) {}

    public function rows(int $organizationId, array $filters): array
    {
        return collect()
            ->merge($this->assignmentRows($organizationId, $filters, 'invoice'))
            ->merge($this->assignmentRows($organizationId, $filters, 'payment_note'))
            ->merge($this->eventInvoiceRows($organizationId, $filters))
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

        if ($type === 'event_invoice') {
            $participant = $this->eventParticipant($organizationId, $id);
            abort_if(blank($participant->invoice_number), 404);
            return $format === 'xml'
                ? $this->eventInvoices->xmlDownload($participant)
                : $this->eventInvoices->download($participant);
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
            if (in_array($row['type'], ['invoice', 'event_invoice'], true) && isset($row['xml_filename'])) {
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
        if ($type === 'event_invoice') {
            $participant = $this->eventParticipant($organizationId, $id);
            return $format === 'xml'
                ? $this->eventInvoices->xml($participant)
                : $this->eventInvoices->pdf($participant);
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
                'source' => 'service',
                'amount' => (float) $row->amount,
                'currency' => $row->currency,
                'filename' => ($type === 'invoice' ? 'factura-' : 'nota-plata-').$row->number.'.pdf',
                'xml_filename' => $type === 'invoice' ? 'efactura-'.$row->number.'.xml' : null,
            ];
        })->all();
    }

    private function eventInvoiceRows(int $organizationId, array $filters): array
    {
        $query = DB::table('event_occurrence_user as eou')
            ->join('event_occurrences as eo', 'eo.id', '=', 'eou.event_occurrence_id')
            ->join('events as e', 'e.id', '=', 'eo.event_id')
            ->join('users as u', 'u.id', '=', 'eou.user_id')
            ->where('eo.organization_id', $organizationId)
            ->whereNotNull('eou.invoice_number')
            ->select([
                'eou.id',
                'eou.invoice_number as number',
                'eou.invoiced_at',
                'eou.created_at',
                'e.id as event_id',
                'e.title as event_title',
                'eo.start_datetime',
                'e.payment_amount as amount',
                'e.payment_type as currency',
                'u.first_name',
                'u.last_name',
                'u.email',
            ]);

        $this->applyDateFilters($query, 'eou.invoiced_at', $filters);

        return $query->get()->map(function ($row): array {
            $name = trim(($row->last_name ?? '').' '.($row->first_name ?? '')) ?: $row->email;
            return [
                'id' => (int) $row->id,
                'type' => 'event_invoice',
                'type_label' => 'Factura',
                'number' => $row->number,
                'date' => Carbon::parse($row->invoiced_at ?? $row->created_at)->toDateString(),
                'member' => $name,
                'description' => EventParticipantInvoiceService::lineName($row->event_title, $row->start_datetime),
                'source' => 'event',
                'event_id' => (int) $row->event_id,
                'amount' => (float) $row->amount,
                'currency' => $row->currency ?: 'RON',
                'filename' => 'factura-'.$row->number.'.pdf',
                'xml_filename' => 'efactura-'.$row->number.'.xml',
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

        $payments = $query->get();
        $targets = $this->paidTargets($organizationId, $payments);

        return $payments->map(function (Payment $payment) use ($targets): array {
            $target = $targets[$payment->model_type][$payment->model_id] ?? null;

            return [
                'id' => $payment->id,
                'type' => 'receipt',
                'type_label' => 'Chitanta',
                'number' => $payment->receipt_number,
                'date' => ($payment->paid_at ?? $payment->created_at)->toDateString(),
                'member' => trim($payment->last_name.' '.$payment->first_name),
                'description' => $target['description'] ?? 'Plata #'.$payment->id,
                'source' => $target['source'] ?? null,
                'amount' => (float) $payment->amount,
                'currency' => $target['currency'] ?? null,
                'filename' => $this->receipts->filename($payment),
            ];
        })->all();
    }

    /**
     * Describe the paid objects (service assignment / event participation) of the
     * given payments in two batched queries, keyed by model_type then model_id.
     *
     * @return array<string, array<int, array{description: string, source: string, currency: ?string}>>
     */
    private function paidTargets(int $organizationId, \Illuminate\Support\Collection $payments): array
    {
        $ids = fn (string $type) => $payments->where('model_type', $type)->pluck('model_id')->filter()->unique()->values()->all();
        $targets = [Payment::MODEL_TYPE_SERVICE_USER => [], Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER => []];

        if (($serviceIds = $ids(Payment::MODEL_TYPE_SERVICE_USER)) !== []) {
            DB::table('service_user as su')
                ->join('services as s', 's.id', '=', 'su.service_id')
                ->whereIn('su.id', $serviceIds)
                ->where('s.organization_id', $organizationId)
                ->get(['su.id', 's.name', 's.currency'])
                ->each(function ($row) use (&$targets): void {
                    $targets[Payment::MODEL_TYPE_SERVICE_USER][(int) $row->id] = ['description' => $row->name, 'source' => 'service', 'currency' => $row->currency];
                });
        }

        if (($eventIds = $ids(Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER)) !== []) {
            DB::table('event_occurrence_user as eou')
                ->join('event_occurrences as eo', 'eo.id', '=', 'eou.event_occurrence_id')
                ->join('events as e', 'e.id', '=', 'eo.event_id')
                ->whereIn('eou.id', $eventIds)
                ->where('eo.organization_id', $organizationId)
                ->get(['eou.id', 'e.title', 'eo.start_datetime', 'e.payment_type'])
                ->each(function ($row) use (&$targets): void {
                    $targets[Payment::MODEL_TYPE_EVENT_OCCURRENCE_USER][(int) $row->id] = [
                        'description' => EventParticipantInvoiceService::lineName($row->title, $row->start_datetime),
                        'source' => 'event',
                        'currency' => $row->payment_type ?: 'RON',
                    ];
                });
        }

        return $targets;
    }

    private function eventParticipant(int $organizationId, int $id): EventParticipant
    {
        return EventParticipant::query()
            ->whereKey($id)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('event_occurrences as eo')
                ->whereColumn('eo.id', 'event_occurrence_user.event_occurrence_id')
                ->where('eo.organization_id', $organizationId))
            ->firstOrFail();
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
