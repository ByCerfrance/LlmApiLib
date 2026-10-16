<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Chat\StreamControl;

interface StreamingChatProviderInterface extends ChatProviderInterface
{
    /** @param ProviderChatRequestInterface $request @param StreamControl $control @return iterable<ProviderStreamEvent> @throws \RuntimeException On interruption or provider failure. */
    public function stream(ProviderChatRequestInterface $request, StreamControl $control): iterable;
}
