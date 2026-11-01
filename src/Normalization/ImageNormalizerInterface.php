<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use RuntimeException;

interface ImageNormalizerInterface
{
    /**
     * Normalize embedded image data without modifying the original content.
     *
     * Preserve the content representation and image detail setting. Remote URLs
     * remain unchanged: normalization must not implicitly download images.
     * Optional backend extensions are checked on use, not on construction.
     *
     * @template T of ImageAsset|ImageContent|ImageUrlContent
     * @param T $image Original image content.
     * @return T Normalized image content of the same type.
     * @throws RuntimeException When the required backend extension is unavailable or image processing fails.
     */
    public function normalize(ImageAsset|ImageContent|ImageUrlContent $image): ImageAsset|ImageContent|ImageUrlContent;
}
