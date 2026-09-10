<?php

namespace App\Service\Services;

use App\Service\Models\ServiceUser;
use Dompdf\Dompdf;
use Dompdf\Options;
use DOMDocument;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ServiceInvoiceService
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
        $assignment->loadMissing(['service.organization', 'user.organization']);
        $service = $assignment->service;
        $user = $assignment->user;
        $organization = $service->organization ?? $user->organization;
        $issuedAt = Carbon::parse($assignment->updated_at ?? $assignment->created_at ?? now());
        $amount = number_format((float) $service->price, 2, '.', '');
        $invoiceNumber = $assignment->invoice_number ?: sprintf('INV%09d', $assignment->id);
        $currency = $service->currency ?: 'RON';

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $invoice = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xml->appendChild($invoice);

        $this->append($xml, $invoice, 'cbc:CustomizationID', 'urn:cen.eu:en16931:2017');
        $this->append($xml, $invoice, 'cbc:ID', $invoiceNumber);
        $this->append($xml, $invoice, 'cbc:IssueDate', $issuedAt->toDateString());
        $this->append($xml, $invoice, 'cbc:InvoiceTypeCode', '380');
        $this->append($xml, $invoice, 'cbc:DocumentCurrencyCode', $currency);
        $this->party($xml, $invoice, 'cac:AccountingSupplierParty', $organization?->name ?? '-', $organization?->cui, $organization?->address, $organization?->email);
        $this->party($xml, $invoice, 'cac:AccountingCustomerParty', trim("{$user->last_name} {$user->first_name}") ?: $user->email, null, null, $user->email);

        $taxTotal = $this->append($xml, $invoice, 'cac:TaxTotal');
        $this->amount($xml, $taxTotal, 'cbc:TaxAmount', '0.00', $currency);

        $legalTotal = $this->append($xml, $invoice, 'cac:LegalMonetaryTotal');
        $this->amount($xml, $legalTotal, 'cbc:LineExtensionAmount', $amount, $currency);
        $this->amount($xml, $legalTotal, 'cbc:TaxExclusiveAmount', $amount, $currency);
        $this->amount($xml, $legalTotal, 'cbc:TaxInclusiveAmount', $amount, $currency);
        $this->amount($xml, $legalTotal, 'cbc:PayableAmount', $amount, $currency);

        $line = $this->append($xml, $invoice, 'cac:InvoiceLine');
        $this->append($xml, $line, 'cbc:ID', '1');
        $quantity = $this->append($xml, $line, 'cbc:InvoicedQuantity', '1');
        $quantity->setAttribute('unitCode', 'H87');
        $this->amount($xml, $line, 'cbc:LineExtensionAmount', $amount, $currency);
        $item = $this->append($xml, $line, 'cac:Item');
        $this->append($xml, $item, 'cbc:Name', $service->name);
        $price = $this->append($xml, $line, 'cac:Price');
        $this->amount($xml, $price, 'cbc:PriceAmount', $amount, $currency);

        return $xml->saveXML();
    }

    public function xmlFilename(ServiceUser $assignment): string
    {
        return 'efactura-'.($assignment->invoice_number ?: $assignment->id).'.xml';
    }

    private function renderHtml(ServiceUser $assignment): string
    {
        $assignment->loadMissing(['service.organization', 'user.organization']);
        $service = $assignment->service;
        $user = $assignment->user;
        $fullName = trim("{$user->last_name} {$user->first_name}") ?: $user->email;
        $amount = number_format((float) $service->price, 2, '.', ',');
        $issuedAt = $assignment->updated_at ?? $assignment->created_at ?? now();

        return view('services.invoice', [
            'assignment' => $assignment,
            'service' => $service,
            'user' => $user,
            'organization' => $service->organization ?? $user->organization,
            'fullName' => $fullName,
            'issuedAt' => Carbon::parse($issuedAt),
            'amount' => $amount,
            'invoiceNumber' => $assignment->invoice_number ?: sprintf('INV%09d', $assignment->id),
        ])->render();
    }

    private function append(DOMDocument $xml, \DOMNode $parent, string $name, ?string $value = null): \DOMElement
    {
        $node = $xml->createElement($name);
        if ($value !== null) {
            $node->appendChild($xml->createTextNode($value));
        }
        $parent->appendChild($node);

        return $node;
    }

    private function amount(DOMDocument $xml, \DOMNode $parent, string $name, string $value, string $currency): void
    {
        $node = $this->append($xml, $parent, $name, $value);
        $node->setAttribute('currencyID', $currency);
    }

    private function party(DOMDocument $xml, \DOMNode $invoice, string $wrapper, string $name, ?string $taxId, ?string $address, ?string $email): void
    {
        $partyWrapper = $this->append($xml, $invoice, $wrapper);
        $party = $this->append($xml, $partyWrapper, 'cac:Party');
        $partyName = $this->append($xml, $party, 'cac:PartyName');
        $this->append($xml, $partyName, 'cbc:Name', $name);
        if ($taxId) {
            $taxScheme = $this->append($xml, $party, 'cac:PartyTaxScheme');
            $this->append($xml, $taxScheme, 'cbc:CompanyID', $taxId);
        }
        if ($address) {
            $postal = $this->append($xml, $party, 'cac:PostalAddress');
            $this->append($xml, $postal, 'cbc:StreetName', $address);
            $this->append($xml, $postal, 'cbc:CountrySubentity', 'RO');
            $country = $this->append($xml, $postal, 'cac:Country');
            $this->append($xml, $country, 'cbc:IdentificationCode', 'RO');
        }
        if ($email) {
            $contact = $this->append($xml, $party, 'cac:Contact');
            $this->append($xml, $contact, 'cbc:ElectronicMail', $email);
        }
    }
}
