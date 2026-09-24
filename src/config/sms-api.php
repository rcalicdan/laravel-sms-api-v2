<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Country Code
    |--------------------------------------------------------------------------
    |
    | The default international dialing code prefixed to local phone numbers
    | when the gateway's 'add_code' option is enabled.
    |
    */
    'country_code' => env('SMS_COUNTRY_CODE', '48'),

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    |
    | The active gateway profile used when no specific gateway is defined
    | during runtime execution.
    |
    */
    'default' => env('SMS_DEFAULT_GATEWAY', 'smsapi_pl'),

    /*
    |--------------------------------------------------------------------------
    | Gateway Profiles
    |--------------------------------------------------------------------------
    |
    | Configure one or multiple SMS providers. Supports GET, POST (JSON or Form),
    | custom headers, authentication tokens, and request wrapping.
    |
    */

    'smsapi_pl' => [
        'method' => 'POST',
        'url' => 'https://api.smsapi.pl/sms.do',
        'params' => [
            'send_to_param_name' => 'to',
            'msg_param_name' => 'message',
            'others' => [
                'from' => env('SMSAPI_SENDER', 'Eco'),
                'format' => 'json',
            ],
        ],
        'headers' => [
            'Authorization' => 'Bearer ' . env('SMSAPI_TOKEN', ''),
        ],
        'json' => false,
        'add_code' => true,
    ],

    'twilio' => [
        'method' => 'POST',
        'url' => 'https://api.twilio.com/2010-04-01/Accounts/' . env('TWILIO_ACCOUNT_SID', '') . '/Messages.json',
        'params' => [
            'send_to_param_name' => 'To',
            'msg_param_name' => 'Body',
            'others' => [
                'From' => env('TWILIO_FROM_NUMBER', ''),
            ],
        ],
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(env('TWILIO_ACCOUNT_SID', '') . ':' . env('TWILIO_AUTH_TOKEN', '')),
        ],
        'json' => false,
        'add_code' => true,
    ],

    'generic_json' => [
        'method' => 'POST',
        'url' => env('SMS_GATEWAY_URL', 'https://api.example.com/v1/sms/send'),
        'params' => [
            'send_to_param_name' => 'phone',
            'msg_param_name' => 'text',
            'others' => [
                'sender' => env('SMS_SENDER_NAME', 'RehabOS'),
            ],
        ],
        'headers' => [
            'Authorization' => 'Bearer ' . env('SMS_GATEWAY_KEY', ''),
            'Accept' => 'application/json',
        ],
        'json' => true,
        'jsonToArray' => false,
        'add_code' => true,
    ],

    'generic_get' => [
        'method' => 'GET',
        'url' => env('SMS_GET_GATEWAY_URL', 'https://api.example.com/sendsms'),
        'params' => [
            'send_to_param_name' => 'msisdn',
            'msg_param_name' => 'message',
            'others' => [
                'api_key' => env('SMS_GET_API_KEY', ''),
                'sender' => env('SMS_SENDER_NAME', 'RehabOS'),
            ],
        ],
        'headers' => [],
        'add_code' => true,
    ],

];
