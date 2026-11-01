<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleNative;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$live = in_array('--live', $argv, true);
$fixture = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==';
$key = $live ? (getenv('GEMINI_API_KEY') ?: throw new RuntimeException('Set GEMINI_API_KEY.')) : 'unused-demo-key';
$reference = $live
    ? ImageAsset::fromLocalFile(
        getenv('REFERENCE_IMAGE') ?: throw new RuntimeException('Set REFERENCE_IMAGE to a local file.'),
        getenv('REFERENCE_MIME') ?: throw new RuntimeException('Set REFERENCE_MIME, for example image/png.'),
    )
    : new ImageAsset('image/png', $fixture);
$client = $live ? new CurlAdapter() : new class($fixture) implements ClientInterface {
    public function __construct(private string $fixture)
    {
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        echo 'Inline image inputs: ' . (count($body['contents'][0]['parts']) - 1) . PHP_EOL;
        return new Response(json_encode([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $this->fixture]],
            ]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20, 'totalTokenCount' => 30],
        ], JSON_THROW_ON_ERROR));
    }
};
$generator = new ImageGenerator(new GoogleNative($key, $client),
    new Model(getenv('GOOGLE_IMAGE_MODEL') ?: 'gemini-3.1-flash-lite-image'));

// Reference-guided generation: the prompt explains how to use each ordered input.
$first = $generator->generate(new ImageRequest(
    'Draw a tiger sewing. Use the supplied image for rounded shapes and bold line style, not its subject.',
    aspectRatio: '1:1', images: [$reference],
));
// One-shot editing: supply the generated inline asset as a new input.
$edited = $generator->generate(new ImageRequest(
    'Simplify the furniture in this image while keeping the character and activity.',
    aspectRatio: '1:1', images: [$first->getImages()[0]],
));
foreach (['generated' => $first, 'edited' => $edited] as $label => $response) {
    foreach ($response->getImages() as $image) {
        echo sprintf('%s: %s, %d bytes', $label, $image->mimeType, strlen($image->getBytes() ?? '')) . PHP_EOL;
    }
    echo 'Reported total tokens: ' . ($response->getUsage()->getTotalTokens() ?? 'unknown') . PHP_EOL;
}
echo $live ? "Two live requests completed.\n" : "Offline only: no API calls made.\n";
