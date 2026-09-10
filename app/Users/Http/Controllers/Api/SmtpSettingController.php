<?php

namespace App\Users\Http\Controllers\Api;

use App\Users\Http\Controllers\Controller;
use App\Users\Http\Requests\StoreSmtpSettingRequest;
use App\Users\Http\Requests\UpdateSmtpSettingRequest;
use App\Users\Http\Resources\SmtpSettingResource;
use App\Users\Models\SmtpSetting;
use Illuminate\Http\JsonResponse;

class SmtpSettingController extends Controller
{
    public function show(): SmtpSettingResource
    {
        $setting = SmtpSetting::query()->first();

        abort_unless($setting, 404);

        return new SmtpSettingResource($setting);
    }

    public function store(StoreSmtpSettingRequest $request): JsonResponse
    {
        if (SmtpSetting::query()->exists()) {
            return response()->json([
                'message' => 'Setările SMTP există deja pentru această organizație. Folosește PUT/PATCH pentru actualizare.',
            ], 422);
        }

        $setting = SmtpSetting::query()->create($request->validated());

        return (new SmtpSettingResource($setting))->response()->setStatusCode(201);
    }

    public function update(UpdateSmtpSettingRequest $request): SmtpSettingResource
    {
        $setting = SmtpSetting::query()->first();

        abort_unless($setting, 404);

        $data = $request->validated();

        if (array_key_exists('password', $data) && blank($data['password'])) {
            unset($data['password']);
        }

        $setting->update($data);

        return new SmtpSettingResource($setting);
    }

    public function destroy(): JsonResponse
    {
        $setting = SmtpSetting::query()->first();

        abort_unless($setting, 404);

        $setting->delete();

        return response()->json(status: 204);
    }
}
