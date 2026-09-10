<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Models\Organization;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function showBySlug(string $slug): JsonResponse
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $organization->id,
                'slug' => $organization->slug,
                'url' => $organization->url,
                'name' => $organization->name,
                'address' => $organization->address,
                'email' => $organization->email,
                'phone' => $organization->phone,
                'web' => $organization->web,
                'cui' => $organization->cui,
                'nr_reg_com' => $organization->nr_reg_com,
                'capital' => $organization->capital,
                'cont' => $organization->cont,
                'bank' => $organization->bank,
                'receipt_code' => $organization->receipt_code,
                'receipt_number' => $organization->receipt_number,
                'invoice_code' => $organization->invoice_code,
                'invoice_number' => $organization->invoice_number,
                'bill_code' => $organization->bill_code,
                'bill_number' => $organization->bill_number,
            ],
        ]);
    }
}
