<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

interface ChatProviderInterface extends ProviderInterface
{
    /**
     * @param ProviderChatRequestInterface $request A resolved single-turn request.
     * @return ChatResultInterface The generated turn, without executing any tools.
     * @throws \InvalidArgumentException For invalid request options.
     * @throws \RuntimeException On transport, provider, or response errors.
     */
    public function complete(ProviderChatRequestInterface $request): ChatResultInterface;
}
