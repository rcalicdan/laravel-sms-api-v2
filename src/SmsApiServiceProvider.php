<?php

namespace Rcalicdan\SmsApi;

use Illuminate\Support\ServiceProvider;
use Rcalicdan\SmsApi\SmsApi;

class SmsApiServiceProvider extends ServiceProvider
{
    protected $defer = false;
    protected $configName = 'sms-api';

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__ . '/config/' . $this->configName . '.php';
        $this->publishes([
            $configPath => config_path($this->configName . '.php')
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $configPath = __DIR__ . '/config/' . $this->configName . '.php';
        $this->mergeConfigFrom($configPath, $this->configName);
        $this->app->bind('smsapi', function () {
            return new SmsApi();
        });
    }
}
