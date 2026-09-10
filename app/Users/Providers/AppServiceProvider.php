<?php

namespace App\Users\Providers;

use App\Notifications\Events\NotificationRequested;
use App\Notifications\Listeners\QueueNotificationDeliveries;
use Illuminate\Support\Facades\Event;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationRequested::class, QueueNotificationDeliveries::class);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(config('security.rate_limits.login_per_minute'))
            ->by($this->rateLimitKey($request, (string) $request->input('email'))));
        RateLimiter::for('callbacks', fn (Request $request) => Limit::perMinute(config('security.rate_limits.callbacks_per_minute'))
            ->by($this->rateLimitKey($request, (string) ($request->header('X-Provider-Id') ?: $request->input('external_reference')))));
        RateLimiter::for('expensive', fn (Request $request) => Limit::perMinute(config('security.rate_limits.expensive_per_minute'))
            ->by($this->rateLimitKey($request, (string) ($request->user()?->getAuthIdentifier() ?: $request->input('email')))));
    }

    private function rateLimitKey(Request $request, string $identity): string
    {
        $organization = $request->input('organization_id') ?: $request->user()?->organization_id ?: $request->header('X-Organization-Id', 'none');

        return hash('sha256', implode('|', [$request->ip(), $organization, mb_strtolower($identity ?: 'anonymous')]));
    }
}
