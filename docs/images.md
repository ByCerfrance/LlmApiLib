# Images

[Back to documentation](README.md)

## Goal

Generate a standalone image with the same code for OpenAI and Gemini (Nano Banana 2), or edit an image through a Gemini conversation.

## Generate a standalone image

Install the packages from [Getting started](getting-started.md). This script makes a real request and writes the first image to `generated-image` in the project root. Set `OPENAI_API_KEY` and `OPENAI_IMAGE_MODEL` (a GPT Image model ID), or run it with the `google` argument and set `GEMINI_API_KEY`.

```php
<?php

declare(strict_types=1);

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\Google\GoogleNative;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;

require __DIR__ . '/vendor/autoload.php';

$env = static fn(string $name): string => getenv($name) ?: throw new RuntimeException("Set $name.");
$generator = ($argv[1] ?? 'openai') === 'google'
    ? new ImageGenerator(new GoogleNative($env('GEMINI_API_KEY'), new CurlAdapter()), new Model('gemini-3.1-flash-image'))
    : new ImageGenerator(new OpenAiImages($env('OPENAI_API_KEY'), new CurlAdapter()), new Model($env('OPENAI_IMAGE_MODEL')));

$response = $generator->generate(new ImageRequest(
    prompt: 'A simple illustration of a red barn in a green field.',
    aspectRatio: '1:1',
));

$image = $response->getImages()[0];
$bytes = $image->getBytes() ?? throw new RuntimeException('Expected inline image data.');
file_put_contents(__DIR__ . '/generated-image', $bytes);
echo 'Saved ' . strlen($bytes) . ' bytes of ' . $image->mimeType . PHP_EOL;
```

Only the provider and the model change. The request, the response and the decorators (retry, failover, usage) are the same.

**Portable settings.** `aspectRatio` is translated by each provider:

| Aspect ratio | OpenAI GPT Image | Gemini |
| --- | --- | --- |
| `1:1`, `3:2`, `2:3` | `1024x1024`, `1536x1024`, `1024x1536` | Sent as is |
| Other ratios (`16:9`, `4:3`…) | Rejected before the request | Sent as is, subject to the model |

DALL-E models have their own sizes (`7:4` and `4:7` for DALL-E 3). Combining `aspectRatio` with an explicit OpenAI `size` is rejected.

**Provider-specific settings** go in `options` and are not portable: OpenAI accepts `size`, `quality`, `output_format`, `output_compression`, `background` and `moderation`; Gemini accepts `imageSize` (for example `1K` or `2K`). An option unknown to the provider is rejected before the request.

**Image count.** OpenAI accepts several images per request (`count`), depending on the model. Gemini returns one image per request: `count` greater than one is rejected rather than silently multiplying calls and costs.

ImageAsset contains either base64 data or a URL reference. `getBytes()` decodes inline data; it does not download URLs. Use the MIME type to choose a file extension rather than assuming PNG.

## Generate and edit with Gemini

Set `GEMINI_API_KEY`. This independent example makes two real requests using the native GenerateContent API and Nano Banana 2's configured model ID.

```php
<?php

declare(strict_types=1);

use Berlioz\Http\Client\Adapter\CurlAdapter;
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\OutputModality;
use ByCerfrance\LlmApiLib\Content\ImageContent;
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
$edited = $chat->chat($first->reply('Make the barn red.'));

foreach ($edited->getResult()->getMessage()->getContent() as $part) {
    if ($part instanceof ImageContent) {
        echo 'Image part: ' . ($part->image->mimeType ?? 'unknown MIME type') . PHP_EOL;
    }
}
```

The edited response contains ordered text/image parts. Use each image's MIME type when saving bytes; do not assume every native image is PNG.

For two branches, call `$first->reply()` twice. Do not reconstruct history from visible text: native history also preserves image data and opaque thought signatures. Keep the configured provider ID and model unchanged.

## Boundaries and offline examples

- `Google` is the compatibility endpoint; use `GoogleNative` for this native image workflow.
- Standalone editing/variation endpoints and image streaming are not implemented.
- ImageUsage, RetryImageGenerator and FailoverImageGenerator provide typed decorators. Image counts and token counts remain separate measurements.
- [examples/images.php](../examples/images.php) (`openai` or `google`) and [examples/google-images.php](../examples/google-images.php) run offline by default. Add `--live` for actual calls. Google performs four calls in that script: generation, edit and two branches.

Related: [Conversations](conversations.md), [Usage and pricing](usage-and-pricing.md), [Providers](providers.md).
