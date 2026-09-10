<?php

namespace App\Events\Services;

use App\Events\Models\EventOccurrence;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class OccurrenceAttendancePdfService
{
    public function download(EventOccurrence $occurrence): Response
    {
        return response($this->pdf($occurrence), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($occurrence).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function pdf(EventOccurrence $occurrence): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->renderHtml($occurrence), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return $pdf->output();
    }

    public function filename(EventOccurrence $occurrence): string
    {
        return 'prezenta-eveniment-'.$occurrence->id.'.pdf';
    }

    private function renderHtml(EventOccurrence $occurrence): string
    {
        $occurrence->loadMissing(['event.category', 'participants']);
        $participants = $occurrence->participants->sortBy([
            ['last_name', 'asc'],
            ['first_name', 'asc'],
        ])->values();
        $statusCounts = collect(['registered', 'attended', 'cancelled', 'no_show'])
            ->mapWithKeys(fn (string $status) => [$status => $participants->where('pivot.status', $status)->count()]);

        return view('events.attendance-pdf', [
            'occurrence' => $occurrence,
            'event' => $occurrence->event,
            'participants' => $participants,
            'statusCounts' => $statusCounts,
            'generatedAt' => Carbon::now(),
        ])->render();
    }
}
