<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

readonly class Generic extends OpenAICompatible
{
    /** @param array<string, mixed> $extraBody */
    public function __construct(
        private string|UriInterface $uri,
        #[SensitiveParameter] string $apiKey,
        ClientInterface $client,
        ?string $id = null,
        array $extraBody = [],
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($apiKey, $client, $id, $extraBody, $logger);
    }

    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::create($this->uri);
    }
}
