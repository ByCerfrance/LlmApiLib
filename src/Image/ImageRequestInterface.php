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
    /** @return string|null Portable aspect ratio such as "1:1", translated by each provider; null for its default. */
    public function getAspectRatio(): ?string;
    /** @return list<ImageAsset> Ordered inline or local-file image inputs; empty for text-only generation. Remote URLs are not accepted. */
    public function getImages(): array;
}
