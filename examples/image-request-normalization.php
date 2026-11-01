<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use ByCerfrance\LlmApiLib\Content\TextFormat;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;
use ByCerfrance\LlmApiLib\Provider\ImageProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use Override;

require dirname(__DIR__) . '/vendor/autoload.php';

$reference = ImageAsset::fromLocalFile(__DIR__ . '/../tests/Content/image.png', 'image/png');
$provider = new readonly class($reference) implements ImageProviderInterface {
    public function __construct(private ImageAsset $fixture)
    {
    }

    #[Override]
    public function getId(): string
    {
        return 'offline';
    }

    #[Override]
    public function generate(ProviderImageRequest $request): ImageResult
    {
        echo 'Sent prompt: ' . $request->request->getPrompt() . PHP_EOL;
        foreach ($request->request->getImages() as $image) {
            $size = getimagesizefromstring($image->getBytes());
            echo sprintf("Sent reference: %d x %d\n", $size[0], $size[1]);
        }

        return new ImageResult([$this->fixture], new TokenUsage(), $this->getId(), $request->model->getName());
    }
};
$generator = new ImageGenerator($provider, new Model('offline-model'), promptNormalizer: new PromptNormalizer(
    targets: [PromptTarget::IMAGE_PROMPT, PromptTarget::IMAGE_REFERENCE],
    textNormalizer: new TextNormalizer(trimText: true, collapseSpaces: true),
    imageNormalizer: new ImageNormalizer(maxWidth: 256, maxHeight: 256),
    textFormat: TextFormat::PLAIN_TEXT,
));
$request = new ImageRequest('  Draw   a farm from this reference.  ', images: [$reference]);
$response = $generator->generate($request);
echo 'Original prompt: ' . $request->getPrompt() . PHP_EOL;
$size = getimagesizefromstring($request->getImages()[0]->getBytes());
echo sprintf("Original reference: %d x %d\n", $size[0], $size[1]);
echo 'Output fixture preserved: ' . ($response->getImages()[0] === $reference ? 'yes' : 'no') . PHP_EOL;
