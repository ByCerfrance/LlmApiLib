# Images

[Back to documentation](README.md)

## Goal

Choose between generating a standalone image and editing an image through a conversation. These use different execution contracts.

## Generate a standalone OpenAI image

Install the packages from [Getting started](getting-started.md). Set `OPENAI_API_KEY` and `OPENAI_IMAGE_MODEL` to an available GPT Image model ID. This script makes a real request and writes the first image to `generated-image.png` in the project root.

```php
<?php

declare(strict_types=1);

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;

require __DIR__ . '/vendor/autoload.php';

$key = getenv('OPENAI_API_KEY') ?: throw new RuntimeException('Set OPENAI_API_KEY.');
$modelName = getenv('OPENAI_IMAGE_MODEL') ?: throw new RuntimeException('Set OPENAI_IMAGE_MODEL.');
$generator = new ImageGenerator(new OpenAiImages($key, new CurlAdapter()), new Model($modelName));
$response = $generator->generate(new ImageRequest(
    prompt: 'A simple illustration of a red barn in a green field.',
    count: 1,
    options: ['output_format' => 'png'],
));

$image = $response->getImages()[0];
$bytes = $image->getBytes() ?? throw new RuntimeException('Expected inline image data.');
file_put_contents(__DIR__ . '/generated-image.png', $bytes);
echo 'Saved ' . strlen($bytes) . ' bytes of ' . $image->mimeType . PHP_EOL;
```

ImageAsset contains either base64 data or a URL reference. `getBytes()` decodes inline data; it does not download URLs. GPT Image models return base64 and do not accept the legacy `response_format` option. Available size/quality/count settings depend on the model.

## Generate and edit with Gemini

Set `GEMINI_API_KEY`. This independent example makes two real requests using the native GenerateContent API and Nano Banana 2's configured model ID.

```php
<?php

declare(strict_types=1);

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleNative;

require __DIR__ . '/vendor/autoload.php';

$key = getenv('GEMINI_API_KEY') ?: throw new RuntimeException('Set GEMINI_API_KEY.');
$chat = new Chat(new GoogleNative($key, new CurlAdapter()), new Model('gemini-3.1-flash-image'));
$request = new ChatRequest(
    conversation: new Conversation(['Draw a farm with a barn.']),
    outputModalities: [OutputModality::TEXT, OutputModality::IMAGE],
    imageConfig: ['aspectRatio' => '1:1', 'imageSize' => '1K'],
);
$first = $chat->chat($request);
$edited = $chat->chat($request->withConversation(
    $first->toConversation()->withNewMessage('Make the barn red.'),
));

foreach ($edited->getResult()->getMessage()->getContent() as $part) {
    if ($part instanceof ImageContent) {
        echo 'Image part: ' . ($part->image->mimeType ?? 'unknown MIME type') . PHP_EOL;
    }
}
```

The edited response contains ordered text/image parts. Use each image's MIME type when saving bytes; do not assume every native image is PNG.

For two branches, create two requests from the same `$first->toConversation()`. Do not reconstruct history from visible text: native history also preserves image data and opaque thought signatures. Keep the configured provider ID and model unchanged.

## Boundaries and offline examples

- `Google` is the compatibility endpoint; use `GoogleNative` for this native image workflow.
- Standalone editing/variation endpoints and image streaming are not implemented.
- ImageUsage, RetryImageGenerator and FailoverImageGenerator provide typed decorators. Image counts and token counts remain separate measurements.
- [examples/images.php](../examples/images.php) and [examples/google-images.php](../examples/google-images.php) run offline by default. Add `--live` for actual calls. Google performs four calls in that script: generation, edit and two branches.

Related: [Conversations](conversations.md), [Usage and pricing](usage-and-pricing.md), [Providers](providers.md).
