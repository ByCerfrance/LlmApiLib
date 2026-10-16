<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

interface ImageGeneratorInterface
{
    /** @param ImageRequestInterface|string $request @return ImageResponseInterface @throws \RuntimeException On generation failure. */
    public function generate(ImageRequestInterface|string $request): ImageResponseInterface;
}
