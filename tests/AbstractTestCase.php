<?php

namespace Rcalicdan\SmsApi\Tests;

use Orchestra\Testbench\TestCase;
use Rcalicdan\SmsApi\SmsApiServiceProvider;

abstract class AbstractTestCase extends TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            SmsApiServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'SmsApi' => \Rcalicdan\SmsApi\SmsApiFacade::class,
        ];
    }
}
