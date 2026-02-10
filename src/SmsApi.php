<?php

namespace Rcalicdan\SmsApi;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use Rcalicdan\SmsApi\Exception\InvalidMethodException;


class SmsApi
{
    protected static $client = null;
    protected $config = array();
    protected $gateway;
    protected $request = '';
    protected $response = '';
    protected $responseCode = '';
    protected $country_code = null;
    protected $wrapperParams = [];

    /**
     * SmsApi constructor.
     */
    public function __construct()
    {
        $this->createClient();
    }

    /**
     * Create new Guzzle Client
     *
     * @return $this
     */
    protected function createClient()
    {
        if (!self::$client) {
            self::$client = new Client;
        }
        return $this;
    }

    /**
     * Set custom gateway
     *
     * @param string $gateway
     * @return $this
     */
    public function gateway($gateway = '')
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * Set custom country code
     *
     * @param string $country_code
     * @return $this
     */
    public function countryCode($country_code = '')
    {
        $this->country_code = $country_code;
        return $this;
    }

    /**
     * Adds wrapper Variables
     *
     * @param array $wrapperVars
     * @return $this
     */
    //Addition
    public function addWrapperParams($wrapperParams)
    {
        $this->wrapperParams = $wrapperParams;
        return $this;
    }

    /**
     * Send message
     *
     * @param $to
     * @param $message
     * @param array $extra_params
     * @param array $extra_headers
     * @return $this
     * @throws InvalidMethodException
     */


    public function sendMessage($to, $message, $extra_params = null, $extra_headers = [])
    {
        // Load the default gateway if none is set
        if ($this->gateway == '') {
            $this->loadDefaultGateway();
        }

        // Load credentials from the configuration
        $this->loadCredentialsFromConfig();

        // Extract configuration values
        $request_method = isset($this->config['method']) ? $this->config['method'] : 'GET';
        $url = $this->config['url'];
        $mobile = $this->config['add_code'] ? $this->addCountryCode($to) : $to;

        // Handle mobile number formatting based on JSON setting
        if (!(isset($this->config['json']) && $this->config['json'])) {
            // Flatten array if JSON is false
            if (is_array($mobile)) {
                $mobile = $this->composeBulkMobile($mobile);
            }
        } else {
            // Transform to array if JSON is true
            if (!is_array($mobile)) {
                $mobile = (isset($this->config['jsonToArray']) ? $this->config['jsonToArray'] : true) ? [$mobile] : $mobile;
            }
        }

        // Prepare parameters and headers
        $params = $this->config['params']['others'];
        $headers = isset($this->config['headers']) ? $this->config['headers'] : [];

        // Check for a wrapper in the configuration
        $wrapper = isset($this->config['wrapper']) ? $this->config['wrapper'] : null;
        $wrapperParams = array_merge($this->wrapperParams, (isset($this->config['wrapperParams']) ? $this->config['wrapperParams'] : []));
        $send_to_param_name = $this->config['params']['send_to_param_name'];
        $msg_param_name = $this->config['params']['msg_param_name'];

        // Build the payload
        if ($wrapper) {
            $send_vars[$send_to_param_name] = $mobile;
            $send_vars[$msg_param_name] = $message;
        } else {
            $params[$send_to_param_name] = $mobile;
            $params[$msg_param_name] = $message;
        }

        // Merge wrapper parameters if applicable
        if ($wrapper && $wrapperParams) {
            $send_vars = array_merge($send_vars, $wrapperParams);
        }

        // Merge extra parameters and headers if provided
        if ($extra_params) {
            $params = array_merge($params, $extra_params);
        }
        if ($extra_headers) {
            $headers = array_merge($headers, $extra_headers);
        }

        // Ensure the Authorization header is properly Base64-encoded for Twilio
        if (isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Basic ' . base64_encode(env('TWILIO_ACCOUNT_SID') . ':' . env('TWILIO_AUTH_TOKEN'));
        }

        try {
            // Build the HTTP request
            $request = new Request($request_method, $url);

            if ($request_method == "GET") {
                $promise = $this->getClient()->sendAsync(
                    $request,
                    [
                        'query' => $params,
                        'headers' => $headers
                    ]
                );
            } elseif ($request_method == "POST") {
                $payload = $wrapper ? array_merge([$wrapper => [$send_vars]], $params) : $params;

                if ((isset($this->config['json']) && $this->config['json'])) {
                    $promise = $this->getClient()->sendAsync(
                        $request,
                        [
                            'json' => $payload,
                            'headers' => $headers
                        ]
                    );
                } else {
                    $promise = $this->getClient()->sendAsync(
                        $request,
                        [
                            'form_params' => $payload, // Use form_params for non-JSON POST requests
                            'headers' => $headers
                        ]
                    );
                }
            } else {
                throw new \InvalidArgumentException("Only GET and POST methods are allowed.");
            }

            // Wait for the response
            $response = $promise->wait();
            $this->response = $response->getBody()->getContents();
            $this->responseCode = $response->getStatusCode();

            // Log the full request and response details
            Log::debug('SMS Gateway Request:', [
                'method' => $request_method,
                'url' => $url,
                'headers' => $headers,
                'payload' => $payload,
            ]);
            Log::debug('SMS Gateway Response:', [
                'status_code' => $this->responseCode,
                'body' => $this->response,
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->response = $response->getBody()->getContents();
                $this->responseCode = $response->getStatusCode();

                // Log the error details
                Log::error('SMS Gateway Error:', [
                    'status_code' => $this->responseCode,
                    'body' => $this->response,
                ]);
            } else {
                // Log the exception message if there's no response
                Log::error('SMS Gateway Exception:', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this;
    }

    /**
     * Load Default Gateway
     *
     * @return $this
     */
    protected function loadDefaultGateway()
    {
        $default_acc = config('sms-api.default', null);
        if ($default_acc) {
            $this->gateway = $default_acc;
        }
        return $this;
    }

    /**
     * Load Credentials from the selected Gateway
     *
     * @return $this
     */
    protected function loadCredentialsFromConfig()
    {
        $gateway = $this->gateway;
        $config_name = 'sms-api.' . $gateway;
        $this->config = config($config_name);
        return $this;
    }

    /**
     * Add country code to mobile
     *
     * @param $mobile
     * @return array|string
     */
    protected function addCountryCode($mobile)
    {
        if (!$this->country_code) {
            $this->country_code = config('sms-api.country_code', '91');
        }
        if (is_array($mobile)) {
            array_walk($mobile, function (&$value, $key) {
                $value = $this->country_code . $value;
            });
            return $mobile;
        }
        return $this->country_code . $mobile;
    }

    /**
     * For multiple mobiles
     *
     * @param $mobile
     * @return string
     */
    protected function composeBulkMobile($mobile)
    {
        return implode(',', $mobile);
    }

    /**
     * Get Client
     *
     * @return GuzzleHttp\Client
     */
    public function getClient()
    {
        return self::$client;
    }

    /**
     * Return Response
     *
     * @return string
     */
    public function response()
    {
        return $this->response;
    }

    /**
     * Return Response Code
     *
     * @return string
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }
}
