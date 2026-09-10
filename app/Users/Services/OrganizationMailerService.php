<?php

namespace App\Users\Services;

use App\Users\Models\Organization;
use Illuminate\Mail\Mailable;

class OrganizationMailerService
{
    /**
     * Register a dynamic Laravel mailer built from the organization's SMTP
     * settings and return its name, or null when the organization has no usable
     * settings (callers should then fall back to the system default mailer).
     */
    public function mailerNameFor(Organization $organization): ?string
    {
        $settings = $organization->smtpSetting;

        if (! $settings || ! $settings->active || blank($settings->host) || blank($settings->from_address)) {
            return null;
        }

        $mailerName = 'organization_'.$organization->id;

        config(["mail.mailers.{$mailerName}" => [
            'transport' => 'smtp',
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption,
            'username' => $settings->username,
            'password' => $settings->password,
        ]]);

        return $mailerName;
    }

    /**
     * Apply the organization's SMTP settings to a Mailable, falling back to
     * the system default mailer/from address when the organization has none.
     */
    public function apply(Mailable $mailable, Organization $organization): Mailable
    {
        $mailerName = $this->mailerNameFor($organization);

        if (! $mailerName) {
            return $mailable;
        }

        $settings = $organization->smtpSetting;

        return $mailable->mailer($mailerName)->from($settings->from_address, $settings->from_name);
    }
}
