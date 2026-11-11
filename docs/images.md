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

Combining `aspectRatio` with an explicit OpenAI `size` is rejected.

**Provider-specific settings** go in `options` and are not portable: OpenAI accepts `size`, `quality`, `output_format`, `output_compression`, `background` and `moderation`; Gemini accepts `imageSize` (for example `1K` or `2K`). An option unknown to the provider is rejected before the request.

**Image count.** OpenAI accepts several images per request (`count`), depending on the model. Gemini returns one image per request: `count` greater than one is rejected rather than silently multiplying calls and costs.

**Model constraints.** The Model's `unsupportedParameters` and `parameterValues` apply before the request. For Gemini, use native paths such as `imageConfig.aspectRatio` or `imageConfig.imageSize`.

**Refusals and interruptions.** A generation that the provider refuses or interrupts throws a ProviderException, even when the response contains an image: that image may be partial. `getKind()` returns `CONTENT_REFUSED` for a refused prompt or image, `getFinishReason()` distinguishes `CONTENT_FILTER`, `RECITATION` (reproduced content) and `LENGTH`, and `getProviderReason()` gives the native code. See [Errors and refusals](errors.md#images-catch-the-failure).

ImageAsset contains base64 data, a local file path, or a URL reference. `getBytes()` decodes inline data or reads a local file; it does not download URLs. `getBase64()` returns inline data or encodes the local file. Use the MIME type to choose a file extension rather than assuming PNG.

## Local image inputs: references and one-shot editing

`ImageGenerator` can apply the shared `PromptNormalizer` before sending a request. Select
`PromptTarget::IMAGE_PROMPT` for its text and `PromptTarget::IMAGE_REFERENCE` for its ordered
reference assets. Defaults preserve the original request. See the
[image-request normalization example](normalization.md#normalize-standalone-image-requests).

`ImageRequest` accepts an ordered `images` list of existing `ImageAsset` objects.
Use `ImageAsset::fromLocalFile($path, $mimeType)` for local regular files, or
`ImageAsset::fromBytes($bytes, $mimeType)` for bytes already in memory. Supply the actual MIME type:
the library does not transcode or inspect the image to infer it. Empty data, invalid image MIME syntax
and URL assets are rejected as request inputs. Local file loading does not accept stream-wrapper URLs.

`fromLocalFile()` retains only the resolved absolute path and MIME type, without reading or caching
the contents. Google reads the file when preparing the HTTP request; missing, unreadable or empty files
then fail before HTTP. Keep the file available and unchanged until generation (including retries) completes:
each attempt reads its current contents. Calling `getBytes()` or `getBase64()` also reads the file.
This defers memory allocation; encoding and JSON serialization still load the complete input into memory.

With a Google `ImageGenerator` configured as above:

```php
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageRequest;

$reference = ImageAsset::fromLocalFile('/absolute/path/style.png', 'image/png');
$subject = ImageAsset::fromLocalFile('/absolute/path/subject.jpg', 'image/jpeg');
$generated = $generator->generate(new ImageRequest(
    'Draw the subject from image 2 with the bold outlines and simple shapes of image 1.',
    images: [$reference, $subject],
    aspectRatio: '1:1',
));
$edited = $generator->generate(new ImageRequest(
    'Keep this composition and simplify the background objects.',
    images: [$generated->getImages()[0]],
));
foreach ($edited->getImages() as $image) {
    $bytes = $image->getBytes(); // Use mimeType when choosing a file extension.
}
$usage = $edited->getUsage(); // Reported prompt, completion, total and cached tokens.
```

Google receives the prompt followed by each image as an `inlineData` part with its MIME type and base64
bytes. Supported input MIME types are `image/png`, `image/jpeg`, `image/webp`, `image/heic`, `image/heif`.
The selected model must support image input and output; explicitly incompatible model capabilities are
rejected before HTTP. Model-specific input counts, sizes and request-size limits still apply at Google;
the library does not resize, upload, split or silently discard images. `count` is the requested **output**
count, not the number of references. Gemini standalone requests still require `count: 1`.

Image inputs are supported by **GoogleNative** and **OpenAiImages** (GPT Image models).
Text-only calls keep their existing behavior.
References guide generation but do not guarantee style fidelity, exact line thickness or character consistency.
Reference roles are described in the prompt; there is no style-strength parameter.

The existing usage pipeline retains Google's reported aggregate counters, including input media in the
reported prompt total. It does not estimate missing counters or expose a per-image/text breakdown. The
existing refusal/interruption exceptions retain reported usage when available. `ImageUsage` continues to
work with these requests. Input images may increase provider charges.

For a single edit, returned inline assets can be reused as above. This does **not** replay native conversation
history or thought signatures; use the conversation workflow below for multi-turn continuation.

**Custom implementations:** `ImageRequestInterface` now requires `getImages(): array`, returning an empty
list for text-only implementations. Custom providers must process non-empty inputs or explicitly reject
them. Existing `ImageRequest` positional arguments are unchanged; `images` is the final optional argument.

Run [examples/google-image-inputs.php](../examples/google-image-inputs.php) offline for a complete example,
or opt into its two real calls with `--live`, `GEMINI_API_KEY`, `REFERENCE_IMAGE`, and `REFERENCE_MIME`.
`GOOGLE_IMAGE_MODEL` optionally overrides `gemini-3.1-flash-lite-image`.

References: [Google image inputs](https://ai.google.dev/gemini-api/docs/image-understanding),
[Google image generation and editing](https://ai.google.dev/gemini-api/docs/image-generation).

### OpenAI reference-guided generation and editing

`OpenAiImages` automatically uses `/v1/images/generations` for text-only requests and
`/v1/images/edits` when `images` is non-empty. Both return the same image response abstraction.
Editing requires a model supporting image input: a Model whose declared capabilities exclude
`Capability::VISION` is rejected locally; unknown capabilities are left to OpenAI.

```php
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\OpenAi\OpenAiImages;

$generator = new ImageGenerator(new OpenAiImages($apiKey, $httpClient), new Model('gpt-image-2.5-sunburst'));
$reference = ImageAsset::fromLocalFile('/absolute/path/reference.png', 'image/png');
$generated = $generator->generate(new ImageRequest(
    'Draw a tiger sewing, using the reference for simple shapes and bold outlines.',
    images: [$reference],
    aspectRatio: '1:1',
));
$edited = $generator->generate(new ImageRequest(
    'Simplify the background while keeping the character and composition.',
    images: [$generated->getImages()[0]],
));
```

This integration accepts 1–16 ordered PNG, JPEG or WebP inputs, transmitted as JSON
`images[].image_url` base64 data URLs. Each complete data URL is limited to 20,971,520 characters
by the documented JSON endpoint contract. Local files are read during request preparation.
Remote URLs, file IDs, masks and markup are not exposed by this integration.
Existing output options, count and aspect-ratio mapping apply; provider/model constraints still apply.
Reported aggregate input/output/total token usage is retained, without inventing or exposing a modality
breakdown. Reference fidelity and exact line thickness are not guaranteed.

This uses the Image API directly, not the conversational Responses image-generation tool.
Automated coverage uses simulated HTTP responses; no live OpenAI acceptance test has been performed.
See the [official edits contract](https://developers.openai.com/api/reference/resources/images/methods/edit).

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
- Google standalone edits use prompt + inline images through GenerateContent. Dedicated OpenAI editing/variation endpoints and image streaming are not implemented.
- ImageUsage, RetryImageGenerator and FailoverImageGenerator provide typed decorators. Image counts and token counts remain separate measurements.
- [examples/images.php](../examples/images.php) (`openai` or `google`) and [examples/google-images.php](../examples/google-images.php) run offline by default. Add `--live` for actual calls. Google performs four calls in that script: generation, edit and two branches.

Related: [Conversations](conversations.md), [Usage and pricing](usage-and-pricing.md), [Providers](providers.md).
