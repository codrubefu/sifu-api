<?php

namespace App\Console\Commands;

use App\Users\Services\DemoOrganizationResetService;
use Illuminate\Console\Command;
use RuntimeException;

class ResetDemoOrganization extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Restore the demo organization and its sample data.';

    public function handle(DemoOrganizationResetService $reset): int
    {
        try {
            $organization = $reset->reset();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Demo organization [{$organization->id}] restored.");

        return self::SUCCESS;
    }
}
