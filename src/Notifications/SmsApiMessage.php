<?php

declare(strict_types=1);

namespace Rcalicdan\SmsApi\Notifications;

class SmsApiMessage
{
    public function __construct(
        public string $content = '',
        public ?array $params = null,
        public array $headers = [],
        public string $type = 'text'
    ) {
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function params(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function headers(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    public function unicode(): self
    {
        $this->type = 'unicode';

        return $this;
    }
}
