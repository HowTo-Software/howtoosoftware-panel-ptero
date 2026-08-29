<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Illuminate\Http\Client\Response;

final class AiProviderException extends \RuntimeException
{
    public const RATE_LIMIT = 'rate_limit';
    public const TIMEOUT = 'timeout';
    public const UNAVAILABLE = 'unavailable';
    public const INVALID_CREDENTIAL = 'invalid_credential';
    public const INVALID_RESPONSE = 'invalid_response';
    public const REQUEST_REJECTED = 'request_rejected';

    public function __construct(
        public readonly string $reason,
        public readonly ?int $status = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct('The AI provider request failed.');
    }

    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $body = mb_strtolower($response->body());

        if ($status === 429) {
            $retryAfter = filter_var($response->header('Retry-After'), FILTER_VALIDATE_INT);

            return new self(self::RATE_LIMIT, $status, $retryAfter === false ? null : (int) $retryAfter);
        }

        if (in_array($status, [401, 403], true) || str_contains($body, 'api_key_invalid') || str_contains($body, 'api key not valid')) {
            return new self(self::INVALID_CREDENTIAL, $status);
        }

        if ($status >= 500) {
            return new self(self::UNAVAILABLE, $status);
        }

        return new self(self::REQUEST_REJECTED, $status);
    }

    public static function fromTransport(\Throwable $exception): self
    {
        $message = mb_strtolower($exception->getMessage());

        return new self(
            str_contains($message, 'timed out') || str_contains($message, 'timeout')
                ? self::TIMEOUT
                : self::UNAVAILABLE,
        );
    }

    public function cooldownSeconds(): int
    {
        return match ($this->reason) {
            self::RATE_LIMIT => max(30, min($this->retryAfter ?? 120, 3600)),
            self::INVALID_CREDENTIAL => 1800,
            self::REQUEST_REJECTED => 300,
            self::UNAVAILABLE => 120,
            self::TIMEOUT, self::INVALID_RESPONSE => 60,
            default => 120,
        };
    }
}
