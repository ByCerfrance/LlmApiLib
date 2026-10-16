<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

interface ImageProviderInterface extends ProviderInterface
{
    /** @param ProviderImageRequest $request @return ImageResult @throws \RuntimeException On provider failure. */
    public function generate(ProviderImageRequest $request): ImageResult;
}
