<?php

namespace App\Events\Services;

use App\Events\Models\Event;
use App\Events\Models\EventParticipant;
use App\Service\Services\InvoiceDocumentRenderer;
use App\Service\Services\ServiceDocumentSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * On-demand invoice for a participation in a paid event. Uses the organization
 * invoice sequence shared with service invoices; amount = events.payment_amount,
 * currency = events.payment_type (fallback RON).
 */
class EventParticipantInvoiceService
{
    public function __construct(
        private readonly InvoiceDocumentRenderer $renderer,
        private readonly ServiceDocumentSequenceService $documentSequences,
    ) {}

    public static function isPayable(?Event $event): bool
    {
        return $event !== null
            && (bool) $event->requires_payment
            && $event->payment_amount !== null
            && (float) $event->payment_amount > 0;
    }

    /**
     * Assigns an invoice number once (idempotent, row-locked) and returns the fresh pivot.
     */
    public function generate(EventParticipant $participant): EventParticipant
    {
        return DB::transaction(function () use ($participant): EventParticipant {
            $locked = DB::table('event_occurrence_user')
                ->where('id', $participant->getKey())
                ->lockForUpdate()
                ->first();
            abort_if($locked === null, 404);

            if (blank($locked->invoice_number)) {
                $organizationId = (int) DB::table('event_occurrences')
                    ->where('id', $locked->event_occurrence_id)
                    ->value('organization_id');

                DB::table('event_occurrence_user')->where('id', $locked->id)->update([
                    'invoice_number' => $this->documentSequences->nextInvoice($organizationId),
                    'invoiced_at' => now(),
                ]);
            }

            return EventParticipant::query()->findOrFail($participant->getKey());
        });
    }

    public function download(EventParticipant $participant): Response
    {
        return response($this->pdf($participant), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($participant).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function xmlDownload(EventParticipant $participant): Response
    {
        return response($this->xml($participant), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$this->xmlFilename($participant).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function pdf(EventParticipant $participant): string
    {
        return $this->renderer->pdf($this->document($participant));
    }

    public function xml(EventParticipant $participant): string
    {
        return $this->renderer->xml($this->document($participant));
    }

    public function filename(EventParticipant $participant): string
    {
        return 'factura-'.($participant->invoice_number ?: 'eveniment-'.$participant->id).'.pdf';
    }

    public function xmlFilename(EventParticipant $participant): string
    {
        return 'efactura-'.($participant->invoice_number ?: 'eveniment-'.$participant->id).'.xml';
    }

    public static function lineName(?string $eventTitle, mixed $occurrenceStart): string
    {
        $date = $occurrenceStart ? Carbon::parse($occurrenceStart)->format('d.m.Y') : null;

        return trim('Eveniment '.($eventTitle ?? '').($date ? ' - '.$date : ''));
    }

    private function document(EventParticipant $participant): array
    {
        $participant->loadMissing([
            'occurrence' => fn ($query) => $query->withoutGlobalScopes(),
            'occurrence.event' => fn ($query) => $query->withoutGlobalScopes(),
            'occurrence.organization',
            'user.organization',
        ]);
        $occurrence = $participant->occurrence;
        $event = $occurrence->event;
        $user = $participant->user;

        return [
            'invoice_number' => $participant->invoice_number ?: sprintf('INV%09d', $participant->id),
            'issued_at' => Carbon::parse($participant->invoiced_at ?? $participant->created_at ?? now()),
            'organization' => $occurrence->organization ?? $user->organization,
            'user' => $user,
            'line_name' => self::lineName($event?->title, $occurrence->start_datetime ?? $occurrence->occurrence_date),
            'line_label' => 'Eveniment',
            'amount' => (float) ($event?->payment_amount ?? 0),
            'currency' => $event?->payment_type ?: 'RON',
            'reference' => 'Participare: #'.$participant->id,
        ];
    }
}
