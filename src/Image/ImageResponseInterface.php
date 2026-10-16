<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;

interface ImageResponseInterface
{
    /** @return non-empty-list<ImageAsset> Generated images. */
    public function getImages(): array;
    /** @return TokenUsageInterface Reported per-call usage. */
    public function getUsage(): TokenUsageInterface;
    /** @return ImageResult Provider result and execution metadata. */
    public function getResult(): ImageResult;
}
