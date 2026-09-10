<?php

namespace App\Service\Services;

use App\Service\Models\ServiceUser;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class PaymentNoteService
{
    public function download(ServiceUser $assignment): Response
    {
        return response($this->pdf($assignment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($assignment).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function pdf(ServiceUser $assignment): string
    {
        $assignment->loadMissing(['service.organization', 'user.organization']);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->renderHtml($assignment), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return $pdf->output();
    }

    private function renderHtml(ServiceUser $assignment): string
    {
        $service = $assignment->service;
        $user = $assignment->user;
        $fullName = trim("{$user->last_name} {$user->first_name}") ?: $user->email;
        $issuedAt = now();
        $amount = number_format((float) $service->price, 2, '.', ',');
        $startDate = $this->formatDate($assignment->start_date ?? $issuedAt);
        $expiresAt = $assignment->expires_at ? $this->formatDate($assignment->expires_at) : 'fara expirare';
        $noteNumber = $assignment->bill_number ?: sprintf('%09d', $assignment->id);
        $cardCode = $user->user_code ?: sprintf('USR%08d', $user->id);
        $orderDetails = sprintf(
            'Comanda service #%d | User #%d | Service #%d | Start: %s | Expira: %s',
            $assignment->id,
            $user->id,
            $service->id,
            $startDate,
            $expiresAt,
        );

        return view('services.payment-note', [
            'assignment' => $assignment,
            'service' => $service,
            'user' => $user,
            'organization' => $service->organization ?? $user->organization,
            'fullName' => $fullName,
            'issuedAt' => $issuedAt,
            'amount' => $amount,
            'startDate' => $startDate,
            'expiresAt' => $expiresAt,
            'noteNumber' => $noteNumber,
            'cardCode' => $cardCode,
            'orderDetails' => $orderDetails,
        ])->render();
    }

    public function filename(ServiceUser $assignment): string
    {
        return 'nota-plata-serviciu-'.$assignment->id.'.pdf';
    }

    private function formatDate(Carbon|string|null $date): string
    {
        return $date ? Carbon::parse($date)->format('d-M-Y') : now()->format('d-M-Y');
    }

}
