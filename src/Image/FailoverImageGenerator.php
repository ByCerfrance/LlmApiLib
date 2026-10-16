<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Provider\ProviderException;
use InvalidArgumentException;
use Override;

readonly class FailoverImageGenerator implements ImageGeneratorInterface
{
    /** @var list<ImageGeneratorInterface> */
    private array $generators;

    public function __construct(ImageGeneratorInterface ...$generators)
    {
        if ($generators === []) {
            throw new InvalidArgumentException('Image failover requires at least one generator.');
        }
        $this->generators = array_values($generators);
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
