<?php

declare(strict_types=1);

namespace Rcalicdan\SmsApi;

use Illuminate\Support\Facades\Facade;

class SmsApiFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'smsapi';
    }
}
