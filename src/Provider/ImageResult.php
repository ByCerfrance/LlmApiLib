<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use InvalidArgumentException;

readonly class ImageResult
{
    /** @param non-empty-list<ImageAsset> $images */
    public function __construct(
        public array $images,
        public TokenUsageInterface $usage,
        public string $providerId,
        public string $modelName,
        public ?string $requestId = null,
    ) {
        if ($images === [] || !array_is_list($images)) {
            throw new InvalidArgumentException('Expected a non-empty image list.');
        }
        foreach ($images as $image) {
            if (!$image instanceof ImageAsset) {
                throw new InvalidArgumentException('Expected ImageAsset results.');
            }
        }
    }
}
