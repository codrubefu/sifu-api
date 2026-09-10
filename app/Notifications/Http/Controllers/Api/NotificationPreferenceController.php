<?php

namespace App\Notifications\Http\Controllers\Api;

use App\Notifications\Models\NotificationPreference;
use App\Notifications\Models\PushDevice;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function preference(Request $request): JsonResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['sms', 'mail', 'push'])], 'scope' => ['required', 'string', 'max:50'], 'subscribed' => ['required', 'boolean']]);
        $preference = NotificationPreference::query()->updateOrCreate(['user_id' => $request->user()->id, 'channel' => $data['channel'], 'scope' => $data['scope']], ['subscribed' => $data['subscribed']]);
        return response()->json(['data' => $preference]);
    }
    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:2048'], 'device_id' => ['nullable', 'string', 'max:255']]);
        $device = PushDevice::query()->updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'device_id' => $data['device_id'] ?? null, 'last_used_at' => now()]);
        return response()->json(['data' => $device], 201);
    }
    public function removeDevice(Request $request, PushDevice $device): JsonResponse
    {
        abort_unless((int) $device->user_id === (int) $request->user()->id, 404); $device->delete();
        return response()->json(status: 204);
    }
}
