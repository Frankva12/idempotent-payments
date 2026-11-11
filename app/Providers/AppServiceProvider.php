<?php

namespace App\Providers;

use App\Payouts\PayoutProvider;
use App\Payouts\Providers\HttpPayoutProvider;
use App\Payouts\Providers\SimulatedPayoutProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PayoutProvider::class, function (): PayoutProvider {
            return match (Config::string('services.payout_provider.driver')) {
                'http' => new HttpPayoutProvider(
                    baseUrl: Config::string('services.payout_provider.url'),
                    apiKey: Config::string('services.payout_provider.key'),
                    timeoutSeconds: Config::integer('services.payout_provider.timeout'),
                ),
                default => new SimulatedPayoutProvider(Cache::store()),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
