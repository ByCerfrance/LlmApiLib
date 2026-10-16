<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Provider\ImageProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use InvalidArgumentException;
use Override;

readonly class ImageGenerator implements ImageGeneratorInterface
{
    public function __construct(private ImageProviderInterface $provider, private ModelInterface $model)
    {
    }

    #[Override]
    public function generate(ImageRequestInterface|string $request): ImageResponseInterface
    {
        if ($this->model->supports(Capability::IMAGE_OUTPUT) === false) {
            throw new InvalidArgumentException('Model does not support image output.');
        }
        return new ImageResponse($this->provider->generate(new ProviderImageRequest($this->model,
            is_string($request) ? new ImageRequest($request) : $request)));
    }
}
