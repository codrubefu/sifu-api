<?php

namespace App\Notifications\Services;

use App\Notifications\Models\NotificationDelivery;
use App\Sms\Services\SmsPortalService;
use App\Users\Models\User;
use App\Users\Services\OrganizationMailerService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class NotificationSender
{
    public function __construct(
        private readonly OrganizationMailerService $organizationMailer,
    ) {
    }

    /** @return array{provider:string,external_id:?string} */
    public function send(NotificationDelivery $delivery): array
    {
        $user = $delivery->user;
        $message = $this->render($delivery);

        return match ($delivery->channel) {
            'sms' => $this->sms($user->phone, $message),
            'mail' => $this->mail($user, $message),
            'push' => $this->push($user, $message),
            default => throw new RuntimeException("Unsupported notification channel: {$delivery->channel}"),
        };
    }

    private function render(NotificationDelivery $delivery): string
    {
        $template = (string) config("notifications.templates.{$delivery->template}", $delivery->template);
        foreach ($delivery->payload as $key => $value) {
            $template = str_replace(':'.$key, (string) $value, $template);
        }
        return $template;
    }

    private function sms(?string $to, string $message): array
    {
        if (! $to || ! app(SmsPortalService::class)->send($to, $message)) throw new RuntimeException('SMS provider rejected the message.');
        return ['provider' => 'smsportal', 'external_id' => null];
    }

    private function mail(User $user, string $message): array
    {
        if (! $user->email) throw new RuntimeException('User has no e-mail address.');

        $mailerName = $user->organization ? $this->organizationMailer->mailerNameFor($user->organization) : null;
        $fromAddress = $mailerName ? $user->organization->smtpSetting?->from_address : null;
        $fromName = $mailerName ? $user->organization->smtpSetting?->from_name : null;

        Mail::mailer($mailerName ?? config('mail.default'))->raw($message, function ($mail) use ($user, $fromAddress, $fromName): void {
            $mail->to($user->email)->subject((string) config('notifications.subject'));
            if ($fromAddress) $mail->from($fromAddress, $fromName);
        });

        return ['provider' => $mailerName ?? (string) config('mail.default'), 'external_id' => null];
    }

    private function push(User $user, string $message): array
    {
        $devices = $user->pushDevices()->get();
        // Transitional compatibility: old tokens remain deliverable until clients register devices.
        if ($devices->isEmpty() && filled($user->push_token)) $devices->push(new \App\Notifications\Models\PushDevice(['token' => $user->push_token]));
        if ($devices->isEmpty()) throw new RuntimeException('User has no push token.');
        $ids = [];
        foreach ($devices as $device) {
            $response = Http::withToken((string) config('services.push.token'))->post((string) config('services.push.endpoint'), ['token' => $device->token, 'message' => $message]);
            if (in_array($response->status(), [404, 410], true)) {
                if ($device->exists) $device->delete();
                continue;
            }
            if ($response->failed()) throw new RuntimeException('Push provider rejected the message.');
            if ($device->exists) $device->update(['last_used_at' => now()]);
            if ($response->json('id')) $ids[] = $response->json('id');
        }
        return ['provider' => 'push', 'external_id' => $ids === [] ? null : implode(',', $ids)];
    }
}
