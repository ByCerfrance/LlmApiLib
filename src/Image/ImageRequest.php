<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use InvalidArgumentException;
use Override;

readonly class ImageRequest implements ImageRequestInterface
{
    /**
     * @param array<string, string|int|bool> $options Provider-specific options, not portable across providers.
     * @param string|null $aspectRatio Portable aspect ratio such as "1:1" or "3:2", translated by each provider.
     * @param list<ImageAsset> $images Ordered local image inputs; their purpose is expressed in the prompt.
     */
    public function __construct(
        private string $prompt,
        private int $count = 1,
        private array $options = [],
        private ?string $aspectRatio = null,
        private array $images = [],
    ) {
        if (trim($prompt) === '' || $count < 1) {
            throw new InvalidArgumentException('Image requests require a prompt and positive count.');
        }
        foreach ($options as $key => $value) {
            if (!is_string($key) || !is_scalar($value) || in_array($key, ['model', 'prompt', 'n', 'stream'], true)) {
                throw new InvalidArgumentException('Invalid or reserved image option.');
            }
        }
        if ($aspectRatio !== null && !preg_match('/^[1-9]\d*:[1-9]\d*$/', $aspectRatio)) {
            throw new InvalidArgumentException('An aspect ratio must look like "16:9".');
        }
        if (!array_is_list($images)) {
            throw new InvalidArgumentException('Image inputs must be an ordered list.');
        }
        foreach ($images as $image) {
            if (!$image instanceof ImageAsset || ($image->base64 === null && $image->localPath === null) || $image->url !== null
                || ($image->base64 !== null && $image->getBytes() === '')
                || !preg_match('~^image/[a-zA-Z0-9][a-zA-Z0-9.+-]*$~D', $image->mimeType ?? '')) {
                throw new InvalidArgumentException('Image inputs require inline data or a local file with an image MIME type; URLs are not accepted.');
            }
        }
    }

    #[Override]
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    #[Override]
    public function getCount(): int
    {
        return $this->count;
    }

    #[Override]
    public function getOptions(): array
    {
        return $this->options;
    }

    #[Override]
    public function getAspectRatio(): ?string
    {
        return $this->aspectRatio;
    }

    #[Override]
    public function getImages(): array
    {
        return $this->images;
    }
}
