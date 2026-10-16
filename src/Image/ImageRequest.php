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
     */
    public function __construct(
        private string $prompt,
        private int $count = 1,
        private array $options = [],
        private ?string $aspectRatio = null,
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
}
