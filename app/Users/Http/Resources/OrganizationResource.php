<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'url' => $this->url,
            'name' => $this->name,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'web' => $this->web,
            'cui' => $this->cui,
            'nr_reg_com' => $this->nr_reg_com,
            'capital' => $this->capital,
            'cont' => $this->cont,
            'bank' => $this->bank,
            'receipt_code' => $this->receipt_code,
            'receipt_number' => $this->receipt_number,
            'invoice_code' => $this->invoice_code,
            'invoice_number' => $this->invoice_number,
            'bill_code' => $this->bill_code,
            'bill_number' => $this->bill_number,
        ];
    }
}
