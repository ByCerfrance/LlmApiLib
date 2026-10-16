<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Usage\Measurement;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use ByCerfrance\LlmApiLib\Usage\UsageCollector;
use ByCerfrance\LlmApiLib\Usage\UsageRecord;
use Override;

readonly class ImageUsage implements ImageGeneratorInterface
{
    public function __construct(private ImageGeneratorInterface $generator, private UsageCollector $collector)
    {
    }

    #[Override]
    public function generate(ImageRequestInterface|string $request): ImageResponseInterface
    {
        $id = bin2hex(random_bytes(16));
        try {
            $response = $this->generator->generate($request);
        } catch (ProviderException $failure) {
            $tokens = UsageRecord::fromTokens($id, $failure->getReportedUsage() ?? new TokenUsage());
            $this->collector->add(new UsageRecord($id, [...$tokens->getMeasurements(), new Measurement('output', 'image', null)], $failure->getProviderId()), 'image');
            throw $failure;
        }
        $result = $response->getResult();
        $tokens = UsageRecord::fromTokens($id, $response->getUsage());
        $this->collector->add(new UsageRecord($id, [...$tokens->getMeasurements(), new Measurement('output', 'image', count($response->getImages()))], $result->providerId, $result->modelName), 'image');
        return $response;
    }
}
