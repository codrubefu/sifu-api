<?php

namespace App\Sms\Http\Controllers\Api;

use App\Sms\Http\Resources\SmsMessageResource;
use App\Sms\Models\SmsMessage;
use App\Users\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmsMessageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $smsMessages = SmsMessage::query()
            ->with(['user', 'service'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->where('destination', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('user_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('service', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('service_id'), fn ($query) => $query->where('service_id', $request->integer('service_id')))
            ->when($request->filled('destination'), fn ($query) => $query->where('destination', 'like', '%'.$request->string('destination')->toString().'%'))
            ->when($request->filled('sent_from'), fn ($query) => $query->whereDate('sent_at', '>=', $request->string('sent_from')->toString()))
            ->when($request->filled('sent_to'), fn ($query) => $query->whereDate('sent_at', '<=', $request->string('sent_to')->toString()))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return SmsMessageResource::collection($smsMessages);
    }
}