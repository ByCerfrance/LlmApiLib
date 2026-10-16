<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Provider\ProviderException;
use Override;

readonly class FailoverImageGenerator implements ImageGeneratorInterface
{
    /** @var non-empty-list<ImageGeneratorInterface> */
    private array $generators;

    public function __construct(ImageGeneratorInterface $generator, ImageGeneratorInterface ...$fallbacks)
    {
        $this->generators = [$generator, ...array_values($fallbacks)];
    }

    #[Override]
    public function generate(ImageRequestInterface|string $request): ImageResponseInterface
    {
        $last = null;
        foreach ($this->generators as $generator) {
            try {
                return $generator->generate($request);
            } catch (ProviderException $failure) {
                if (!$failure->isTransient()) {
                    throw $failure;
                }
                $last = $failure;
            }
        }
        throw $last;
    }
}
