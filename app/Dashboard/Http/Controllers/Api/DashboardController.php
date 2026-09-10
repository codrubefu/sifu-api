<?php

namespace App\Dashboard\Http\Controllers\Api;

use App\Dashboard\Services\DashboardService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function show(Request $request, DashboardService $dashboard): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'group_by' => ['sometimes', Rule::in(['day', 'month'])],
        ]);

        return response()->json([
            'data' => $dashboard->summary($request->user()->organization_id, $filters),
        ]);
    }
}
