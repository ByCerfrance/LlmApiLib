<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use Override;
use RuntimeException;

/** Selects Imagick when available, otherwise GD, without requiring either at construction. */
readonly class ImageNormalizer implements ImageNormalizerInterface
{
    private ImagickImageNormalizer $imagick;
    private GdImageNormalizer $gd;

    public function __construct(
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?int $quality = null,
        ?string $format = null,
    ) {
        $this->imagick = new ImagickImageNormalizer($maxWidth, $maxHeight, $quality, $format);
        $this->gd = new GdImageNormalizer($maxWidth, $maxHeight, $quality, $format);
    }

    #[Override]
    public function normalize(ImageContent|ImageUrlContent $image): ImageContent|ImageUrlContent
    {
        return match (true) {
            extension_loaded('imagick') => $this->imagick->normalize($image),
            extension_loaded('gd') => $this->gd->normalize($image),
            default => throw new RuntimeException('The imagick or gd extension is required to use ImageNormalizer.'),
        };
    }
}
