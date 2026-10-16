<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use InvalidArgumentException;
use Override;

readonly class ImageRequest implements ImageRequestInterface
{
    /** @param array<string, string|int|bool> $options */
    public function __construct(private string $prompt, private int $count = 1, private array $options = [])
    {
        if (trim($prompt) === '' || $count < 1) {
            throw new InvalidArgumentException('Image requests require a prompt and positive count.');
        }
        foreach ($options as $key => $value) {
            if (!is_string($key) || !is_scalar($value) || in_array($key, ['model', 'prompt', 'n', 'stream'], true)) {
                throw new InvalidArgumentException('Invalid or reserved image option.');
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
}
