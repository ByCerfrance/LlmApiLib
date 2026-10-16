<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Normalized provider failure.
 *
 * Only safe HTTP metadata is retained: provider error bodies may echo submitted content or identifiers.
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        private readonly FailureKind $kind = FailureKind::UNKNOWN,
        private readonly ?int $retryAfterMs = null,
        private readonly ?string $requestId = null,
        private readonly ?string $providerId = null,
        private readonly ?TokenUsageInterface $reportedUsage = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a failure from an unsuccessful HTTP response.
     *
     * Only the status, retry delay, request ID and reported token usage are kept.
     */
    public static function fromResponse(string $providerId, ResponseInterface $response): self
    {
        return self::fromHttpStatus(
            $providerId,
            $response->getStatusCode(),
            $response->getHeaderLine('Retry-After'),
            $response->getHeaderLine('x-request-id'),
            self::reportedUsage((string) $response->getBody()),
        );
    }

    /**
     * Create a failure from HTTP metadata, e.g. when the body is a stream that cannot be read.
     */
    public static function fromHttpStatus(
        string $providerId,
        int $status,
        string $retryAfter = '',
        string $requestId = '',
        ?TokenUsageInterface $reportedUsage = null,
    ): self {
        $kind = match (true) {
            in_array($status, [401, 403], true) => FailureKind::AUTHENTICATION,
            $status === 429 => FailureKind::RATE_LIMIT,
            in_array($status, [408, 500, 502, 503, 504], true) => FailureKind::UNAVAILABLE,
            $status >= 400 && $status < 500 => FailureKind::INVALID_REQUEST,
            default => FailureKind::UNKNOWN,
        };

        return new self(
            sprintf('Provider "%s" returned HTTP %d.', $providerId, $status),
            code: $status,
            kind: $kind,
            retryAfterMs: self::retryAfterMs(trim($retryAfter)),
            requestId: $requestId ?: null,
            providerId: $providerId,
            reportedUsage: $reportedUsage,
        );
    }

    public function getKind(): FailureKind
    {
        return $this->kind;
    }

    public function getReportedUsage(): ?TokenUsageInterface
    {
        return $this->reportedUsage;
    }

    public function getRetryAfterMs(): ?int
    {
        return $this->retryAfterMs;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function isTransient(): bool
    {
        return in_array($this->kind, [FailureKind::TRANSPORT, FailureKind::RATE_LIMIT, FailureKind::UNAVAILABLE], true);
    }

    private static function retryAfterMs(string $retryAfter): ?int
    {
        if ($retryAfter === '') {
            return null;
        }
        if (ctype_digit($retryAfter)) {
            $seconds = min((float) intdiv(PHP_INT_MAX, 1000000), (float) $retryAfter);

            return (int) $seconds * 1000;
        }
        if (($date = strtotime($retryAfter)) !== false) {
            return (int) min(intdiv(PHP_INT_MAX, 1000000), max(0, $date - time())) * 1000;
        }

        return null;
    }

    private static function reportedUsage(string $body): ?TokenUsage
    {
        $data = json_decode($body, true);
        $usage = is_array($data) ? ($data['usage'] ?? null) : null;
        if (!is_array($usage)) {
            return null;
        }
        $values = [];
        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $key) {
            $value = $usage[$key] ?? null;
            $values[] = is_int($value) && $value >= 0 ? $value : null;
        }
        $details = $usage['prompt_tokens_details'] ?? null;
        $cached = is_array($details) ? ($details['cached_tokens'] ?? null) : null;
        $values[] = is_int($cached) && $cached >= 0 ? $cached : null;

        return new TokenUsage(...$values);
    }
}
