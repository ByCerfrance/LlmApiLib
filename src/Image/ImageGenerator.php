<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Model\ModelInterface;
use ByCerfrance\LlmApiLib\Normalization\NullPromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizerInterface;
use ByCerfrance\LlmApiLib\Provider\ImageProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use InvalidArgumentException;
use Override;

readonly class ImageGenerator implements ImageGeneratorInterface
{
    public function __construct(
        private ImageProviderInterface $provider,
        private ModelInterface $model,
        private PromptNormalizerInterface $promptNormalizer = new NullPromptNormalizer(),
    ) {
    }

    #[Override]
    public function generate(ImageRequestInterface|string $request): ImageResponseInterface
    {
        if ($this->model->supports(Capability::IMAGE_OUTPUT) === false) {
            throw new InvalidArgumentException('Model does not support image output.');
        }
        $request = is_string($request) ? new ImageRequest($request) : $request;

        return new ImageResponse($this->provider->generate(new ProviderImageRequest($this->model, $this->promptNormalizer->normalize($request))));
    }
}
