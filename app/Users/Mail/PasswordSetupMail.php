<?php

namespace App\Users\Mail;

use App\Users\Models\User;
use App\Users\Services\OrganizationMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordSetupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $link;

    public function __construct(
        public User $user,
        string $token,
        public bool $isNewAccount = true,
    ) {
        $frontendUrl = $user->organization?->url ?: config('app.frontend_url');

        $this->link = self::toOrigin((string) $frontendUrl)
            .'/set-password?token='.$token
            .'&email='.urlencode((string) $user->email);
    }

    /**
     * Reduce a configured frontend URL down to its origin (scheme + host + port),
     * dropping any path/query/fragment. `organizations.url` and `FRONTEND_URL` are
     * meant to hold just the origin the SPA is served from; a value mistakenly
     * copied from a browser address bar (e.g. "http://host/erp/members") would
     * otherwise get baked into every password-setup link and 404 in the SPA,
     * which only registers `/set-password` at the site root.
     */
    private static function toOrigin(string $url): string
    {
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return rtrim($url, '/');
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    public function build(): self
    {
        $mail = $this
            ->subject($this->isNewAccount ? 'Bine ai venit! Setează-ți parola' : 'Resetare parolă')
            ->view('emails.users.password-setup');

        if ($this->user->organization) {
            app(OrganizationMailerService::class)->apply($mail, $this->user->organization);
        }

        return $mail;
    }
}
