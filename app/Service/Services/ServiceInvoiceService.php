<?php

namespace App\Service\Services;

use App\Service\Models\ServiceUser;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ServiceInvoiceService
{
    public function __construct(private readonly InvoiceDocumentRenderer $renderer) {}

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
        return $this->renderer->pdf($this->document($assignment));
    }

    public function filename(ServiceUser $assignment): string
    {
        return 'factura-'.($assignment->invoice_number ?: $assignment->id).'.pdf';
    }

    public function xmlDownload(ServiceUser $assignment): Response
    {
        return response($this->xml($assignment), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$this->xmlFilename($assignment).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function xml(ServiceUser $assignment): string
    {
        return $this->renderer->xml($this->document($assignment));
    }

    public function xmlFilename(ServiceUser $assignment): string
    {
        return 'efactura-'.($assignment->invoice_number ?: $assignment->id).'.xml';
    }

    private function document(ServiceUser $assignment): array
    {
        $assignment->loadMissing(['service.organization', 'user.organization']);
        $service = $assignment->service;
        $user = $assignment->user;

        return [
            'invoice_number' => $assignment->invoice_number ?: sprintf('INV%09d', $assignment->id),
            'issued_at' => Carbon::parse($assignment->updated_at ?? $assignment->created_at ?? now()),
            'organization' => $service->organization ?? $user->organization,
            'user' => $user,
            'line_name' => $service->name,
            'line_label' => 'Serviciu',
            'amount' => (float) $service->price,
            'currency' => $service->currency ?: 'RON',
            'reference' => 'Assignment: #'.$assignment->id,
        ];
    }
}
