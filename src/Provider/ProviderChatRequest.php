<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\ChatRequestInterface;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use Override;

readonly class ProviderChatRequest implements ProviderChatRequestInterface
{
    public function __construct(
        private ModelInterface $model,
        private ChatRequestInterface $request,
    ) {
    }

    #[Override]
    public function getModel(): ModelInterface
    {
        return $this->model;
    }

    #[Override]
    public function getRequest(): ChatRequestInterface
    {
        return $this->request;
    }
}
