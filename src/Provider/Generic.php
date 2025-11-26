<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;
use SensitiveParameter;

readonly class Generic extends AbstractProvider
{
    public function __construct(
        private string|UriInterface $uri,
        #[SensitiveParameter] string $apiKey,
        string $model,
        ClientInterface $client,
        ?array $capabilities = null,
    ) {
        parent::__construct($apiKey, $model, $client, $capabilities);
    }

    #[Override]
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        return Uri::create($this->uri);
    }
}
