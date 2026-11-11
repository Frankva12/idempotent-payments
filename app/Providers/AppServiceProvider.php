<?php

namespace App\Providers;

use App\Payouts\PayoutProvider;
use App\Payouts\Providers\HttpPayoutProvider;
use App\Payouts\Providers\SimulatedPayoutProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PayoutProvider::class, function (Application $app): PayoutProvider {
            $config = $app['config']->get('services.payout_provider');

            return match ($config['driver']) {
                'http' => new HttpPayoutProvider(
                    baseUrl: (string) $config['url'],
                    apiKey: (string) $config['key'],
                    timeoutSeconds: (int) $config['timeout'],
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
