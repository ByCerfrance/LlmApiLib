<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

interface ImageRequestInterface
{
    /** @return string Image prompt. */
    public function getPrompt(): string;
    /** @return int Requested image count. */
    public function getCount(): int;
    /** @return array<string, string|int|bool> Provider-specific generation options. */
    public function getOptions(): array;
}
