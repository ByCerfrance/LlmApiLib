<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Content;

use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Model\Capability;
use Override;

readonly class ImageContent implements ContentInterface
{
    public function __construct(public ImageAsset $image)
    {
    }

    #[Override]
    public function jsonSerialize(bool $encapsulated = false): array
    {
        return ['type' => 'image_url', 'image_url' => ['url' => $this->image->url ?? sprintf('data:%s;base64,%s', $this->image->mimeType, $this->image->base64)]];
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return [Capability::VISION];
    }
}
