<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use InvalidArgumentException;

readonly class ImageAsset
{
    public function __construct(public ?string $mimeType, public ?string $base64 = null, public ?string $url = null)
    {
        if (($base64 === null) === ($url === null) || ($mimeType !== null && !str_starts_with($mimeType, 'image/'))
            || ($base64 !== null && ($base64 === '' || $mimeType === null || base64_decode($base64, true) === false))
            || ($url !== null && $url === '')) {
            throw new InvalidArgumentException('An image requires either valid base64 data with an image MIME type or a URL.');
        }
    }

    public function getBytes(): ?string
    {
        return $this->base64 === null ? null : base64_decode($this->base64, true);
    }
}
