<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use Psr\Http\Message\ResponseInterface;

/** @internal Normalizes safe HTTP metadata without retaining response bodies. */
readonly class Failure
{
    public static function fromResponse(string $providerId, ResponseInterface $response): ProviderException
    {
        $status = $response->getStatusCode();
        $kind = match (true) {
            in_array($status, [401, 403], true) => FailureKind::AUTHENTICATION,
            $status === 429 => FailureKind::RATE_LIMIT,
            in_array($status, [408, 500, 502, 503, 504], true) => FailureKind::UNAVAILABLE,
            $status >= 400 && $status < 500 => FailureKind::INVALID_REQUEST,
            default => FailureKind::UNKNOWN,
        };
        $retry = trim($response->getHeaderLine('Retry-After'));
        $delay = null;
        if ($retry !== '') {
            if (ctype_digit($retry)) {
                $seconds = min((float) intdiv(PHP_INT_MAX, 1000000), (float) $retry);
                $delay = (int) $seconds * 1000;
            } elseif (($date = strtotime($retry)) !== false) {
                $delay = (int) min(intdiv(PHP_INT_MAX, 1000000), max(0, $date - time())) * 1000;
            }
        }

        return new ProviderException(
            sprintf('Provider "%s" returned HTTP %d.', $providerId, $status),
            code: $status,
            kind: $kind,
            retryAfterMs: $delay,
            requestId: $response->getHeaderLine('x-request-id') ?: null,
            providerId: $providerId,
            reportedUsage: self::reportedUsage($response),
        );
    }

    private static function reportedUsage(ResponseInterface $response): ?TokenUsage
    {
        $data = json_decode((string) $response->getBody(), true);
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
