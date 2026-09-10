<?php

namespace App\Service\Jobs;

use App\Sms\Models\SmsMessage;
use App\Sms\Services\SmsPortalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Users\Models\AuditLog;
use App\Users\Models\User;
use App\Users\Services\BusinessActivityLogger;

class SendExpiringServiceSms implements ShouldQueue
{
    use Queueable;

    public function handle(SmsPortalService $smsPortalService): void
    {
        $noticeDays = max(0, (int) config('service.expiration_notice_days', 1));
        $targetDate = now()->addDays($noticeDays)->toDateString();
        DB::table('service_user')
            ->join('users', 'users.id', '=', 'service_user.user_id')
            ->join('services', 'services.id', '=', 'service_user.service_id')
            ->whereDate('service_user.expires_at', $targetDate)
            ->where('services.is_active', true)
            ->where('users.active', true)
            ->whereNotNull('users.phone')
            ->whereRaw("TRIM(users.phone) <> ''")
            ->select([
                'service_user.id as service_user_id',
                'service_user.expires_at',
                'users.id as user_id',
                'users.phone as user_phone',
                'services.id as service_id',
                'services.name as service_name',
            ])
            ->orderBy('service_user.id')
            ->chunkById(100, function ($assignments) use ($smsPortalService): void {
                foreach ($assignments as $assignment) {
                    $this->sendNotice($assignment, $smsPortalService);
                }
            }, 'service_user.id', 'service_user_id');
    }

    private function sendNotice(object $assignment, SmsPortalService $smsPortalService): void
    {
        $message = $this->message($assignment->service_name, $assignment->expires_at);
        $smsMessage = SmsMessage::query()->firstOrCreate(
            [
                'type' => SmsMessage::TYPE_SERVICE_EXPIRING,
                'service_user_id' => $assignment->service_user_id,
            ],
            [
                'user_id' => $assignment->user_id,
                'service_id' => $assignment->service_id,
                'destination' => trim($assignment->user_phone),
                'message' => $message,
                'status' => SmsMessage::STATUS_PENDING,
            ]
        );

        if ($smsMessage->status === SmsMessage::STATUS_SENT) {
            return;
        }

        $destination = trim($assignment->user_phone);
        $sent = $smsPortalService->send($destination, $message);

        $smsMessage->forceFill([
            'user_id' => $assignment->user_id,
            'service_id' => $assignment->service_id,
            'destination' => $destination,
            'message' => $message,
            'status' => $sent ? SmsMessage::STATUS_SENT : SmsMessage::STATUS_FAILED,
            'sent_at' => $sent ? Carbon::now() : null,
        ])->save();

        if ($sent) {
            $user = User::query()->withoutGlobalScopes()->find($assignment->user_id);
            if ($user !== null) {
                app(BusinessActivityLogger::class)->record(AuditLog::SMS_SENT, $user, $smsMessage, [], [
                    'type' => $smsMessage->type,
                    'service_id' => $smsMessage->service_id,
                    'sent_at' => $smsMessage->sent_at,
                ]);
            }
        }
    }

    private function message(string $serviceName, string $expiresAt): string
    {
        return strtr((string) config('service.expiration_notice_message'), [
            ':service' => $serviceName,
            ':expires_at' => $expiresAt,
        ]);
    }
}
