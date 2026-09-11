<?php

namespace App\Console\Commands;

use App\Users\Services\OrganizationSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowOrganizationSubscription extends Command
{
    protected $signature = 'organization:subscription:show {organization : Organization ID} {--month= : Usage month YYYY-MM}';
    protected $description = 'Show organization subscription, effective limits, usage and event generation blocks.';

    public function handle(OrganizationSubscriptionService $subscriptions): int
    {
        $id = $this->argument('organization');
        $month = $this->option('month') ?? now()->format('Y-m');
        if (! ctype_digit((string) $id) || ! DB::table('organizations')->where('id', $id)->exists() || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('Invalid organization or month (YYYY-MM).');
            return self::FAILURE;
        }
        $this->line(json_encode($subscriptions->show((int) $id, $month), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
