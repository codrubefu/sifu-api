<?php

namespace App\Sms\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SmsPortalService
{
    public function __construct(
        private readonly ?string $endpoint = null,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly ?int $encoding = null,
        private readonly ?int $language = null,
        private readonly ?int $timeout = null,
    ) {}

    public function send(string $destination, string $message): bool
    {
        $response = Http::timeout($this->configuredTimeout())
            ->get($this->configuredEndpoint(), [
                'Dest' => $destination,
                'Msg' => $this->plainAsciiMessage($message),
                'User' => $this->configuredUsername(),
                'Pass' => $this->configuredPassword(),
                'Enc' => $this->configuredEncoding(),
                'La' => $this->configuredLanguage(),
            ]);

        return $response->successful();
    }

    public function plainAsciiMessage(string $message): string
    {
        $message = Str::ascii($message);
        $message = preg_replace('/[^\x20-\x7E]/', '', $message) ?? '';

        return trim(preg_replace('/\s+/', ' ', $message) ?? '');
    }

    private function configuredEndpoint(): string
    {
        return $this->endpoint ?: (string) config('services.smsportal.endpoint');
    }

    private function configuredUsername(): string
    {
        $username = $this->username ?: config('services.smsportal.user');

        if (! is_string($username) || $username === '') {
            throw new InvalidArgumentException('SMS Portal username is not configured.');
        }

        return $username;
    }

    private function configuredPassword(): string
    {
        $password = $this->password ?: config('services.smsportal.password');

        if (! is_string($password) || $password === '') {
            throw new InvalidArgumentException('SMS Portal password is not configured.');
        }

        return $password;
    }

    private function configuredEncoding(): int
    {
        return $this->encoding ?? (int) config('services.smsportal.encoding', 0);
    }

    private function configuredLanguage(): int
    {
        return $this->language ?? (int) config('services.smsportal.language', 1733);
    }

    private function configuredTimeout(): int
    {
        return $this->timeout ?? (int) config('services.smsportal.timeout', 10);
    }
}
