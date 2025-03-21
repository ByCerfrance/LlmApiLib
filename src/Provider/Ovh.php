<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use Berlioz\Http\Message\Uri;
use Psr\Http\Message\UriInterface;

readonly class Ovh extends Mistral
{
    protected function createUri(): UriInterface
    {
        $model = strtolower($this->model);
        $model = str_replace(['.', ' '], '-', $model);

        return Uri::createFromString(
            sprintf(
                'https://%s.endpoints.kepler.ai.cloud.ovh.net/api/openai_compat/v1/chat/completions',
                $model
            )
        );
    }
}
