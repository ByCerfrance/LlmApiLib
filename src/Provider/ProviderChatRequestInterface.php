<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Model\ModelInterface;

interface ProviderChatRequestInterface
{
    /** @return ModelInterface The model selected by the executor. */
    public function getModel(): ModelInterface;

    /** @return ChatRequestInterface History and options after resolving defaults. */
    public function getRequest(): ChatRequestInterface;
}
