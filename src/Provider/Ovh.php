<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use Psr\Http\Message\UriInterface;

readonly class Ovh extends AbstractProvider
{
    protected function createUri(CompletionInterface $completion): UriInterface
    {
        $model = strtolower($completion->getModel() ?? $this->model);
        $model = str_replace(['.', ' '], '-', $model);

        return Uri::createFromString(
            sprintf(
                'https://%s.endpoints.kepler.ai.cloud.ovh.net/api/openai_compat/v1/chat/completions',
                $model
            )
        );
    }

    protected function createBody(CompletionInterface $completion): array
    {
        $body = parent::createBody($completion);
        $body['model'] = null;

        return $body;
    }
}
