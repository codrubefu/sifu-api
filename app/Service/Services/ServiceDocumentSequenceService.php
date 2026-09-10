<?php

namespace App\Service\Services;

use App\Users\Models\Organization;

class ServiceDocumentSequenceService
{
    public function nextInvoice(int $organizationId): string
    {
        $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
        $nextNumber = (int) $organization->invoice_number + 1;

        $organization->forceFill(['invoice_number' => $nextNumber])->save();

        return sprintf('%s%06d', $organization->invoice_code ?: 'INV', $nextNumber);
    }

    public function nextBill(int $organizationId): string
    {
        $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
        $nextNumber = (int) $organization->bill_number + 1;

        $organization->forceFill(['bill_number' => $nextNumber])->save();

        return sprintf('%s%06d', $organization->bill_code ?: 'BILL', $nextNumber);
    }
}
