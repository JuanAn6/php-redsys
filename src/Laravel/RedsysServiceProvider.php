<?php

declare(strict_types=1);

namespace JuanAn6\Redsys\Laravel;

use Illuminate\Support\ServiceProvider;
use JuanAn6\Redsys\Config;
use JuanAn6\Redsys\Redsys;

class RedsysServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/redsys.php', 'redsys');

        $this->app->singleton(Config::class, fn ($app) => Config::fromArray($app['config']->get('redsys', [])));
        $this->app->singleton(Redsys::class, fn ($app) => new Redsys($app->make(Config::class)));
        $this->app->alias(Redsys::class, 'redsys');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/redsys.php' => $this->app->configPath('redsys.php'),
            ], 'redsys-config');
        }
    }
}
