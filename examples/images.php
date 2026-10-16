<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleNative;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;
use InvalidArgumentException;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

// Usage: php examples/images.php [openai|google] [--live]
$live = in_array('--live', $argv, true);
$providerName = in_array('google', $argv, true) ? 'google' : 'openai';
$pixel = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==';
$client = $live ? new CurlAdapter() : new class($pixel) implements ClientInterface {
    public function __construct(private readonly string $pixel)
    {
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response(str_contains((string) $request->getUri(), 'googleapis.com')
            ? json_encode(['candidates' => [['content' => ['role' => 'model', 'parts' => [
                ['inlineData' => ['mimeType' => 'image/png', 'data' => $this->pixel]],
            ]], 'finishReason' => 'STOP']]], JSON_THROW_ON_ERROR)
            : json_encode(['data' => [['b64_json' => $this->pixel]]], JSON_THROW_ON_ERROR));
    }
};
$key = static fn(string $name): string => $live ? (getenv($name) ?: throw new RuntimeException(sprintf('Set %s for live generation.', $name))) : 'unused-demo-key';

// Only the provider and the model change; the generation code is the same.
$generator = match ($providerName) {
    'openai' => new ImageGenerator(new OpenAiImages($key('OPENAI_API_KEY'), $client), new Model('gpt-image-1')),
    'google' => new ImageGenerator(new GoogleNative($key('GEMINI_API_KEY'), $client), new Model('gemini-3.1-flash-image')),
    default => throw new InvalidArgumentException('Choose openai or google.'),
};
$response = $generator->generate(new ImageRequest('A simple illustration of a red barn', aspectRatio: '1:1'));
foreach ($response->getImages() as $image) {
    echo $providerName . ' ' . $image->mimeType . ': ' . strlen($image->getBytes() ?? '') . ' bytes' . PHP_EOL;
}
echo $live ? 'Live generation completed.' . PHP_EOL : 'Offline only: no API calls made.' . PHP_EOL;
