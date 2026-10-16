<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Request;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Single-request JSON transport shared by provider implementations.
 *
 * Transport errors and unsuccessful statuses become normalized ProviderException failures.
 */
readonly class JsonTransport
{
    public function __construct(private ClientInterface $client)
    {
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     *
     * @throws ProviderException
     */
    public function post(string $provider, string $url, array $body, array $headers): ResponseInterface
    {
        return $this->send($provider, new Request(
            method: Request::HTTP_METHOD_POST,
            uri: $url,
            body: json_encode($body, JSON_THROW_ON_ERROR),
            headers: ['Content-Type' => 'application/json', ...$headers],
        ));
    }

    /**
     * @throws ProviderException
     */
    public function send(string $provider, RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $failure) {
            throw new ProviderException(sprintf('Transport failure for provider "%s".', $provider), previous: $failure,
                kind: FailureKind::TRANSPORT, providerId: $provider);
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw ProviderException::fromResponse($provider, $response);
        }

        return $response;
    }

    /**
     * @return array<mixed>
     * @throws ProviderException
     */
    public function decode(ResponseInterface $response, string $provider): array
    {
        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $failure) {
            throw new ProviderException('The provider returned invalid JSON.', previous: $failure, kind: FailureKind::INVALID_RESPONSE,
                requestId: $response->getHeaderLine('x-request-id') ?: null, providerId: $provider);
        }
        if (!is_array($data)) {
            throw new ProviderException('Expected a JSON response object.', kind: FailureKind::INVALID_RESPONSE,
                requestId: $response->getHeaderLine('x-request-id') ?: null, providerId: $provider);
        }

        return $data;
    }
}
