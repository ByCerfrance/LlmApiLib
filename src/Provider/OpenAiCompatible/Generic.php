<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAiCompatible;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Model\ParameterPolicy;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

readonly class Generic extends AbstractOpenAiCompatible
{
    /** @param array<string, mixed> $extraBody */
    public function __construct(
        private string|UriInterface $uri,
        #[SensitiveParameter] string $apiKey,
        ClientInterface $client,
        ?string $id = null,
        array $extraBody = [],
        ?LoggerInterface $logger = null,
        ParameterPolicy $parameterPolicy = ParameterPolicy::STRICT,
    ) {
        parent::__construct($apiKey, $client, $id, $extraBody, $logger, $parameterPolicy);
    }

    #[Override]
    protected function createUri(): UriInterface
    {
        return Uri::create($this->uri);
    }
}
