<?php

declare(strict_types=1);

use Rcalicdan\SmsApi\SmsApi;

if (! function_exists('smsapi')) {
    /**
     * @param string|list<string>|null $to
     * @param array<string, mixed>|null $extraParams
     * @param array<string, string> $headers
     */
    function smsapi(
        string|array|null $to = null,
        ?string $message = null,
        ?array $extraParams = null,
        array $headers = []
    ): SmsApi|string {
        /** @var SmsApi */
        $smsApi = app('smsapi');

        if ($to !== null && $message !== null) {
            return $smsApi->sendMessage($to, $message, $extraParams, $headers)->response();
        }

        return $smsApi;
    }
}
