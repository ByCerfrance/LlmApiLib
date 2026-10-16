<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use Berlioz\Http\Client\Adapter\CurlAdapter;
use Berlioz\Http\Message\Response;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$live = in_array('--live', $argv, true);
$key = $live ? (getenv('OPENAI_API_KEY') ?: getenv('OPENAI_APIKEY') ?: throw new RuntimeException('Set OPENAI_API_KEY for live generation.')) : 'unused-demo-key';
$client = $live ? new CurlAdapter() : new class implements ClientInterface {
    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return new Response('{"data":[{"b64_json":"iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=="}]}');
    }
};
$generator = new ImageGenerator(new OpenAiImages($key, $client), new Model('gpt-image-1'));
$response = $generator->generate(new ImageRequest('A simple illustration of a red barn', options: ['output_format' => 'png']));
foreach ($response->getImages() as $image) {
    echo $image->mimeType . ': ' . strlen($image->getBytes() ?? '') . ' bytes' . PHP_EOL;
}
echo $live ? 'Live generation completed.' . PHP_EOL : 'Offline only: no API calls made.' . PHP_EOL;
