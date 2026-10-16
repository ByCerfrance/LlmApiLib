<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

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
        );
    }
}
