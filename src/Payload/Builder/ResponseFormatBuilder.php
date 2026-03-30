<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\ResponseFormat\ResponseFormatInterface;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;

readonly class ResponseFormatBuilder implements BuilderInterface
{
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof ResponseFormatInterface;
    }

    /**
     * @param ResponseFormatInterface $value
     */
    public function build(mixed $value, PayloadBuilder $payloadBuilder, BuildContext $context): mixed
    {
        return $value->jsonSerialize();
    }
}
