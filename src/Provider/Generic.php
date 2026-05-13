<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\Completion\ServiceTier;
use ByCerfrance\LlmApiLib\Model\ModelInfo;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

readonly class Generic extends AbstractProvider
{
    public function __construct(
        private string|UriInterface $uri,
        #[SensitiveParameter] string $apiKey,
        ModelInfo|string $model,
        ClientInterface $client,
        ?ServiceTier $serviceTier = null,
        array $extraBody = [],
        ?array $capabilities = null,
        ?string $id = null,
        array $labels = [],
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct(
            apiKey: $apiKey,
            model: $model,
            client: $client,
            serviceTier: $serviceTier,
            extraBody: $extraBody,
            capabilities: $capabilities,
            id: $id,
            labels: $labels,
            logger: $logger,
        );
    }

    #[Override]
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        return Uri::create($this->uri);
    }
}
