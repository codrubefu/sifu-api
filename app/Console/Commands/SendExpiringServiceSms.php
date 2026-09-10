<?php

namespace App\Console\Commands;

use App\Service\Jobs\SendExpiringServiceSms as SendExpiringServiceSmsJob;
use Illuminate\Console\Command;

class SendExpiringServiceSms extends Command
{
    protected $signature = 'services:send-expiring-sms
        {--sync : Run the job immediately instead of queueing it}';

    protected $description = 'Send SMS notifications for services that are about to expire.';

    public function handle(): int
    {
        $job = new SendExpiringServiceSmsJob;

        if ($this->option('sync')) {
            app()->call([$job, 'handle']);

            $this->info('Expiring service SMS job ran synchronously.');

            return self::SUCCESS;
        }

        dispatch($job);

        $this->info('Expiring service SMS job was queued.');
        $this->line('Run php artisan queue:work --once to process it now.');

        return self::SUCCESS;
    }
}