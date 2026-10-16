<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Model\ModelInterface;
use Override;

/** Binds a fallback route to an application-selected model. */
readonly class ModelProvider implements ChatProviderInterface
{
    public function __construct(private ChatProviderInterface $provider, private ModelInterface $model)
    {
    }

    #[Override]
    public function getId(): string
    {
        return $this->provider->getId();
    }

    #[Override]
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface
    {
        return $this->provider->complete(new ProviderChatRequest($this->model, $request->getRequest()));
    }
}
