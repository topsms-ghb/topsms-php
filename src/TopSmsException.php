<?php

declare(strict_types=1);

namespace TopSms;

/**
 * Výjimka TopSMS API — nese HTTP kód a surovou odpověď serveru.
 */
class TopSmsException extends \RuntimeException
{
    /** @var int */
    private $httpCode;

    /** @var array<string,mixed>|null */
    private $response;

    /**
     * @param array<string,mixed>|null $response
     */
    public function __construct(string $message, int $httpCode = 0, ?array $response = null)
    {
        parent::__construct($message, $httpCode);
        $this->httpCode = $httpCode;
        $this->response = $response;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}
