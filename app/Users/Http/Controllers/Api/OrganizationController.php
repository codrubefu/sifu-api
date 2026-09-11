<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Resources\OrganizationResource;
use App\Users\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function showBySlug(string $slug): OrganizationResource
    {
        $organization = Organization::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return OrganizationResource::make($organization);
    }

    public function showByUrl(Request $request): OrganizationResource
    {
        $request->validate([
            'url' => ['required', 'string'],
        ]);

        $normalized = rtrim(mb_strtolower(trim($request->query('url', ''))), '/');

        $organization = Organization::query()
            ->whereRaw('LOWER(url) = ?', [$normalized])
            ->orWhereRaw('LOWER(url) = ?', [$normalized.'/'])
            ->firstOrFail();

        return OrganizationResource::make($organization);
    }
}
