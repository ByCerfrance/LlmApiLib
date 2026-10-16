<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\FinishReason;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Normalized provider failure.
 *
 * Only safe metadata is retained: provider error bodies may echo submitted content or identifiers.
 */
class ProviderException extends RuntimeException
{
    /** Provider error codes and native reasons that are known to mean a content refusal. */
    private const REFUSAL_CODES = ['moderation_blocked', 'content_policy_violation', 'content_filter'];

    private readonly ?string $providerReason;

    /**
     * @param FinishReason|null $finishReason Normalized reason why the generation stopped, when the provider gave one.
     * @param string|null $providerReason Native reason code, for diagnostics; anything but a short code is discarded.
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        private readonly FailureKind $kind = FailureKind::UNKNOWN,
        private readonly ?int $retryAfterMs = null,
        private readonly ?string $requestId = null,
        private readonly ?string $providerId = null,
        private readonly ?TokenUsageInterface $reportedUsage = null,
        private readonly ?FinishReason $finishReason = null,
        ?string $providerReason = null,
    ) {
        parent::__construct($message, $code, $previous);
        // Codes only: free text could echo the submitted content.
        $this->providerReason = $providerReason !== null && preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $providerReason) === 1 ? $providerReason : null;
    }

    /**
     * Create a failure from an unsuccessful HTTP response.
     *
     * Only the status, retry delay, request ID, reported token usage and error code are kept.
     */
    public static function fromResponse(string $providerId, ResponseInterface $response): self
    {
        $data = json_decode((string) $response->getBody(), true);
        $data = is_array($data) ? $data : [];

        return self::fromHttpStatus(
            $providerId,
            $response->getStatusCode(),
            $response->getHeaderLine('Retry-After'),
            $response->getHeaderLine('x-request-id'),
            self::reportedUsage($data),
            self::errorCode($data),
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
        ?string $providerReason = null,
    ): self {
        $refused = $status >= 400 && $status < 500 && in_array($providerReason, self::REFUSAL_CODES, true);
        $kind = match (true) {
            $refused => FailureKind::CONTENT_REFUSED,
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
            finishReason: $refused ? FinishReason::CONTENT_FILTER : null,
            providerReason: $providerReason,
        );
    }

    public function getKind(): FailureKind
    {
        return $this->kind;
    }

    /** @return FinishReason|null Normalized reason why the generation stopped, for example LENGTH for a truncated output. */
    public function getFinishReason(): ?FinishReason
    {
        return $this->finishReason;
    }

    /** @return string|null Native code of the provider, such as "moderation_blocked" or "IMAGE_SAFETY". */
    public function getProviderReason(): ?string
    {
        return $this->providerReason;
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

    /** @param array<mixed> $data */
    private static function errorCode(array $data): ?string
    {
        $error = $data['error'] ?? null;
        if (!is_array($error)) {
            return null;
        }
        // OpenAI-style string code first, then the Google-style status.
        foreach ([$error['code'] ?? null, $error['status'] ?? null] as $code) {
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        return null;
    }

    /** @param array<mixed> $data */
    private static function reportedUsage(array $data): ?TokenUsage
    {
        $usage = $data['usage'] ?? null;
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
