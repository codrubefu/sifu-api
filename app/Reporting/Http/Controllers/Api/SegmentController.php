<?php

namespace App\Reporting\Http\Controllers\Api;

use App\Reporting\Http\Requests\StoreSegmentRequest;
use App\Reporting\Models\Segment;
use App\Reporting\Services\SegmentService;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SegmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => Segment::query()->where('organization_id', $request->user()->organization_id)->latest()->get()]);
    }

    public function store(StoreSegmentRequest $request): JsonResponse
    {
        $segment = Segment::query()->create($request->validated() + [
            'organization_id' => $request->user()->organization_id, 'created_by' => $request->user()->id,
        ]);
        return response()->json(['data' => $segment], 201);
    }

    public function update(StoreSegmentRequest $request, Segment $segment): JsonResponse
    {
        $this->owned($request, $segment);
        $segment->update($request->validated());
        return response()->json(['data' => $segment]);
    }

    public function destroy(Request $request, Segment $segment): JsonResponse
    {
        $this->owned($request, $segment);
        $segment->delete();
        return response()->json([], 204);
    }

    public function members(Request $request, Segment $segment, SegmentService $segments): JsonResponse
    {
        $this->owned($request, $segment);
        $members = $segments->members($segment)->paginate($request->integer('per_page', 15));
        return response()->json($members);
    }

    private function owned(Request $request, Segment $segment): void
    {
        abort_unless((int) $segment->organization_id === (int) $request->user()->organization_id, 404);
    }
}
