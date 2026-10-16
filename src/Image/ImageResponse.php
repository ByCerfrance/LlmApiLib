<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Usage\TokenUsageInterface;
use Override;

readonly class ImageResponse implements ImageResponseInterface
{
    public function __construct(private ImageResult $result)
    {
    }

    #[Override]
    public function getImages(): array
    {
        return $this->result->images;
    }

    #[Override]
    public function getUsage(): TokenUsageInterface
    {
        return $this->result->usage;
    }

    #[Override]
    public function getResult(): ImageResult
    {
        return $this->result;
    }
}
