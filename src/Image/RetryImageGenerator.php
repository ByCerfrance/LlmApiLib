<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Resilience\RetryPolicy;
use ByCerfrance\LlmApiLib\Resilience\RetryRunner;
use Override;
use Throwable;

readonly class RetryImageGenerator implements ImageGeneratorInterface
{
    public function __construct(
        private ImageGeneratorInterface $generator,
        private RetryPolicy $policy = new RetryPolicy(),
        private RetryRunner $runner = new RetryRunner(),
    ) {
    }

    #[Override]
    public function generate(ImageRequestInterface|string $request): ImageResponseInterface
    {
        return $this->runner->run(fn(): ImageResponseInterface => $this->generator->generate($request),
            fn(Throwable $failure, int $attempt): ?int => $failure instanceof ProviderException ? $this->policy->delay($attempt, $failure) : null);
    }
}
