<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Request;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/** @internal Shared single-request transport for native and image endpoints. */
readonly class JsonTransport
{
    public function __construct(private ClientInterface $client)
    {
    }

    /** @param array<string, mixed> $body @param array<string, string> $headers */
    public function post(string $provider, string $url, array $body, array $headers): ResponseInterface
    {
        $request = new Request(method: 'POST', uri: $url, body: json_encode($body, JSON_THROW_ON_ERROR), headers: ['Content-Type' => 'application/json', ...$headers]);
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $failure) {
            throw new ProviderException('Provider transport failed.', previous: $failure, kind: FailureKind::TRANSPORT, providerId: $provider);
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw Failure::fromResponse($provider, $response);
        }

        return $response;
    }

    public function decode(ResponseInterface $response, string $provider): array
    {
        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $failure) {
            throw new ProviderException('Provider returned invalid JSON.', previous: $failure, kind: FailureKind::INVALID_RESPONSE,
                requestId: $response->getHeaderLine('x-request-id') ?: null, providerId: $provider);
        }
        if (!is_array($data)) {
            throw new ProviderException('Expected a JSON response object.', kind: FailureKind::INVALID_RESPONSE, providerId: $provider);
        }

        return $data;
    }
}
