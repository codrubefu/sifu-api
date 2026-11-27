<?php

namespace App\Service\Services;

use App\Users\Models\Organization;
use App\Users\Models\User;
use DOMDocument;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;

/**
 * Renders a single-line invoice (PDF via Dompdf, UBL 2.1 / e-Factura XML) for any
 * billable object (service assignment, event participation).
 *
 * Expected document keys:
 *  - invoice_number (string), issued_at (Carbon), organization (?Organization),
 *  - user (User, the customer), line_name (string), line_label (string, table header),
 *  - amount (float|string), currency (string), reference (string, e.g. "Assignment: #12").
 */
class InvoiceDocumentRenderer
{
    /**
     * @param  array{invoice_number: string, issued_at: Carbon, organization: ?Organization, user: User, line_name: string, line_label: string, amount: float|string, currency: string, reference: string}  $document
     */
    public function pdf(array $document): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($document), 'UTF-8');
        $pdf->setPaper('a4');
        $pdf->render();

        return $pdf->output();
    }

    /**
     * @param  array{invoice_number: string, issued_at: Carbon, organization: ?Organization, user: User, line_name: string, line_label: string, amount: float|string, currency: string, reference: string}  $document
     */
    public function xml(array $document): string
    {
        $organization = $document['organization'];
        $user = $document['user'];
        $amount = number_format((float) $document['amount'], 2, '.', '');
        $currency = $document['currency'];

        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $invoice = $xml->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $invoice->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xml->appendChild($invoice);

        $this->append($xml, $invoice, 'cbc:CustomizationID', 'urn:cen.eu:en16931:2017');
        $this->append($xml, $invoice, 'cbc:ID', $document['invoice_number']);
        $this->append($xml, $invoice, 'cbc:IssueDate', $document['issued_at']->toDateString());
        $this->append($xml, $invoice, 'cbc:InvoiceTypeCode', '380');
        $this->append($xml, $invoice, 'cbc:DocumentCurrencyCode', $currency);
        $this->party($xml, $invoice, 'cac:AccountingSupplierParty', $organization?->name ?? '-', $organization?->cui, $organization?->address, $organization?->email);
        $this->party($xml, $invoice, 'cac:AccountingCustomerParty', $this->customerName($user), null, null, $user->email);

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
        $this->append($xml, $item, 'cbc:Name', $document['line_name']);
        $price = $this->append($xml, $line, 'cac:Price');
        $this->amount($xml, $price, 'cbc:PriceAmount', $amount, $currency);

        return $xml->saveXML();
    }

    private function html(array $document): string
    {
        $user = $document['user'];

        return view('services.invoice', [
            'organization' => $document['organization'],
            'user' => $user,
            'fullName' => $this->customerName($user),
            'issuedAt' => $document['issued_at'],
            'amount' => number_format((float) $document['amount'], 2, '.', ','),
            'currency' => $document['currency'],
            'invoiceNumber' => $document['invoice_number'],
            'lineName' => $document['line_name'],
            'lineLabel' => $document['line_label'],
            'reference' => $document['reference'],
        ])->render();
    }

    private function customerName(User $user): string
    {
        return trim("{$user->last_name} {$user->first_name}") ?: (string) $user->email;
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
