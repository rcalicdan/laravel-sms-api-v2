<?php

declare(strict_types=1);

namespace Rcalicdan\SmsApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class SmsApi
{
    protected static ?ClientInterface $client = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected string $gateway = '';

    protected string $response = '';

    protected int|string $responseCode = 0;

    protected ?string $countryCode = null;

    /**
     * @var array<string, mixed>
     */
    protected array $wrapperParams = [];

    public function __construct(?ClientInterface $client = null)
    {
        if ($client !== null) {
            self::$client = $client;
        } elseif (self::$client === null) {
            self::$client = new Client([
                'timeout' => 10.0,
                'http_errors' => true,
            ]);
        }
    }

    public function gateway(string $gateway = ''): self
    {
        $this->gateway = $gateway;

        return $this;
    }

    public function countryCode(string $countryCode = ''): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * @param array<string, mixed> $wrapperParams
     */
    public function addWrapperParams(array $wrapperParams): self
    {
        $this->wrapperParams = $wrapperParams;

        return $this;
    }

    /**
     * Send SMS Message.
     *
     * @param string|list<string> $to
     * @param array<string, mixed>|null $extraParams
     * @param array<string, string> $extraHeaders
     */
    public function sendMessage(
        string|array $to,
        string $message,
        ?array $extraParams = null,
        array $extraHeaders = []
    ): self {
        $this->ensureGatewayConfigured();

        $recipients = $this->prepareRecipients($to);
        $headers = $this->prepareHeaders($extraHeaders);
        $payload = $this->preparePayload($recipients, $message, $extraParams);
        $method = $this->resolveHttpMethod();
        $url = (string) ($this->config['url'] ?? '');

        return $this->executeRequest($method, $url, $payload, $headers);
    }

    public function getClient(): ClientInterface
    {
        return self::$client ?? new Client();
    }

    public function response(): string
    {
        return $this->response;
    }

    public function getResponseCode(): int|string
    {
        return $this->responseCode;
    }

    private function ensureGatewayConfigured(): void
    {
        if ($this->gateway === '') {
            $default = config('sms-api.default');
            if (\is_string($default) && $default !== '') {
                $this->gateway = $default;
            }
        }

        $this->config = config("sms-api.{$this->gateway}", []);
    }

    /**
     * @param string|list<string> $to
     *
     * @return string|list<string>
     */
    private function prepareRecipients(string|array $to): string|array
    {
        $addCode = (bool) ($this->config['add_code'] ?? false);
        $mobile = $addCode ? $this->addCountryCodeTo($to) : $to;

        $isJson = (bool) ($this->config['json'] ?? false);
        $jsonToArray = (bool) ($this->config['jsonToArray'] ?? true);

        if (! $isJson) {
            return \is_array($mobile) ? implode(',', $mobile) : $mobile;
        }

        if (! \is_array($mobile) && $jsonToArray) {
            return [$mobile];
        }

        return $mobile;
    }

    /**
     * @param string|list<string> $mobile
     *
     * @return string|list<string>
     */
    private function addCountryCodeTo(string|array $mobile): string|array
    {
        $code = $this->countryCode ?? (string) config('sms-api.country_code', '48');

        if (\is_array($mobile)) {
            return array_map(
                fn (string $num): string => str_starts_with($num, '+') ? $num : $code . $num,
                $mobile
            );
        }

        return str_starts_with($mobile, '+') ? $mobile : $code . $mobile;
    }

    /**
     * @param array<string, string> $extraHeaders
     *
     * @return array<string, string>
     */
    private function prepareHeaders(array $extraHeaders): array
    {
        $headers = $this->config['headers'] ?? [];

        if (! empty($extraHeaders)) {
            $headers = array_merge($headers, $extraHeaders);
        }

        return $headers;
    }

    /**
     * @param string|list<string> $recipients
     * @param array<string, mixed>|null $extraParams
     *
     * @return array<string, mixed>
     */
    private function preparePayload(string|array $recipients, string $message, ?array $extraParams): array
    {
        $sendToParamName = (string) ($this->config['params']['send_to_param_name'] ?? 'to');
        $msgParamName = (string) ($this->config['params']['msg_param_name'] ?? 'message');
        $params = $this->config['params']['others'] ?? [];
        $wrapper = $this->config['wrapper'] ?? null;

        if ($extraParams !== null) {
            $params = array_merge($params, $extraParams);
        }

        if ($wrapper) {
            $sendVars = [
                $sendToParamName => $recipients,
                $msgParamName => $message,
            ];

            $wrapperParams = array_merge($this->wrapperParams, $this->config['wrapperParams'] ?? []);
            if (! empty($wrapperParams)) {
                $sendVars = array_merge($sendVars, $wrapperParams);
            }

            return array_merge([$wrapper => [$sendVars]], $params);
        }

        $params[$sendToParamName] = $recipients;
        $params[$msgParamName] = $message;

        return $params;
    }

    private function resolveHttpMethod(): string
    {
        $method = strtoupper((string) ($this->config['method'] ?? 'GET'));

        if (! \in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            throw new InvalidArgumentException("HTTP method [{$method}] is not supported by SmsApi.");
        }

        return $method;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function executeRequest(string $method, string $url, array $payload, array $headers): self
    {
        $isJson = (bool) ($this->config['json'] ?? false);
        $options = ['headers' => $headers];

        if ($method === 'GET') {
            $options['query'] = $payload;
        } else {
            $options[$isJson ? 'json' : 'form_params'] = $payload;
        }

        try {
            $response = $this->getClient()->request($method, $url, $options);
            $this->handleSuccessResponse($response, $method, $url);
        } catch (BadResponseException $e) {
            $this->handleBadResponse($e);
        } catch (GuzzleException $e) {
            $this->handleConnectionError($e);
        } catch (Throwable $e) {
            $this->handleUnexpectedError($e);
        }

        return $this;
    }

    private function handleSuccessResponse(ResponseInterface $response, string $method, string $url): void
    {
        $this->response = $response->getBody()->getContents();
        $this->responseCode = $response->getStatusCode();

        Log::debug('SMS Gateway Request Successful:', [
            'gateway' => $this->gateway,
            'method' => $method,
            'url' => $url,
            'status_code' => $this->responseCode,
        ]);
    }

    private function handleBadResponse(BadResponseException $e): void
    {
        $response = $e->getResponse();
        $this->response = $response->getBody()->getContents();
        $this->responseCode = $response->getStatusCode();

        Log::error('SMS Gateway HTTP Error Response:', [
            'gateway' => $this->gateway,
            'status_code' => $this->responseCode,
            'response' => $this->response,
        ]);
    }

    private function handleConnectionError(GuzzleException $e): void
    {
        $this->responseCode = (int) $e->getCode();
        $this->response = $e->getMessage();

        Log::error('SMS Gateway Network / Connection Error:', [
            'gateway' => $this->gateway,
            'message' => $e->getMessage(),
        ]);
    }

    private function handleUnexpectedError(Throwable $e): void
    {
        $this->responseCode = 500;
        $this->response = $e->getMessage();

        Log::error('SMS Gateway Unexpected Exception:', [
            'gateway' => $this->gateway,
            'message' => $e->getMessage(),
        ]);
    }
}
