<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use RuntimeException;
use Throwable;

class ProviderException extends RuntimeException
{
    /**
     * Maximum length (in characters) of the response body retained for diagnostics.
     *
     * Provider error bodies may echo back submitted parameters, organization
     * identifiers or other sensitive context. The body is truncated to limit
     * accidental leakage through logs, exceptions and error reporters.
     */
    public const int MAX_BODY_LENGTH = 2048;

    private const string TRUNCATION_SUFFIX = '… [truncated]';

    private string $body;

    public function __construct(
        string $message,
        string $body = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly FailureKind $kind = FailureKind::UNKNOWN,
        private readonly ?int $retryAfterMs = null,
        private readonly ?string $requestId = null,
        private readonly ?string $providerId = null,
        private readonly ?TokenUsageInterface $reportedUsage = null,
    ) {
        $this->body = self::truncateBody($body);

        parent::__construct($message, $code, $previous);
    }

    public function getBody(): string
    {
        return $this->body;
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

    /**
     * Truncate a response body to {@see self::MAX_BODY_LENGTH} characters.
     */
    public static function truncateBody(string $body): string
    {
        if (mb_strlen($body) <= self::MAX_BODY_LENGTH) {
            return $body;
        }

        return mb_substr($body, 0, self::MAX_BODY_LENGTH) . self::TRUNCATION_SUFFIX;
    }
}
