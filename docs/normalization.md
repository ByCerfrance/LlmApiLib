# Normalization

[Back to documentation](README.md)

## Goal

Remove unnecessary formatting with explicit, deterministic rules. Normalization runs locally without an LLM call. Fewer bytes do not guarantee fewer tokens: savings depend on the model's tokenizer.

Use `TextNormalizer` directly, configure independent input/output pipelines on `Chat`, or share a `PromptNormalizer` with `ImageGenerator`. All examples in the first three sections are offline.

## Minify JSON without changing its values

This complete example is **offline**. Save it in your project root after installing the library.

```php
<?php

declare(strict_types=1);

use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

require __DIR__ . '/vendor/autoload.php';

$normalizer = new TextNormalizer(minifyJson: true);
$original = <<<'JSON'
{
    "description": "Keep  these  spaces",
    "amount": 1.2300,
    "identifier": 123456789012345678901234567890
}
JSON;

$normalized = $normalizer->normalize($original);
echo $normalized . PHP_EOL;
echo 'Original bytes: ' . strlen($original) . PHP_EOL;
echo 'Normalized bytes: ' . strlen($normalized) . PHP_EOL;
```

The normalized JSON is:

```json
{"description":"Keep  these  spaces","amount":1.2300,"identifier":123456789012345678901234567890}
```

The normalizer validates the whole document and removes only JSON whitespace outside strings. It does not decode and re-encode values: large numbers, decimal notation, key order, duplicate keys and string escapes retain their original representation. The original PHP string remains unchanged.

## Clean explicitly declared plain text

This variant reuses the autoloader from the complete example:

```php
use ByCerfrance\LlmApiLib\Normalization\TextFormat;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$normalizer = new TextNormalizer(
    trimText: true,
    trimLineEnds: true,
    collapseSpaces: true,
    maxConsecutiveBlankLines: 1,
    normalizeLineEndings: true,
);

$text = "  Explain   crop\t rotation.  \r\n\r\n\r\nUse  two sentences.  ";
echo $normalizer->normalize($text, TextFormat::PLAIN_TEXT);
```

Output (with LF line endings and no trailing spaces):

```text
Explain crop rotation.

Use two sentences.
```

`PLAIN_TEXT` is an explicit application decision. Message roles do not imply a format: a user message or tool result may contain code, Markdown or indentation-sensitive data.

## Preserve code, Markdown and unknown formats

The default format is `TextFormat::UNKNOWN`. Even with all cleanup options enabled, an unknown document is preserved unless it is a complete valid JSON object or array and `minifyJson` is enabled.

```php
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$normalizer = new TextNormalizer(minifyJson: true, collapseSpaces: true);

$python = "if ready:\n    print('keep  spaces')\n";
$markdown = "A Markdown line break  \nNext line\n";
$fencedJson = "```json\n{ \"value\": 42 }\n```";

var_dump($normalizer->normalize($python) === $python);         // true
var_dump($normalizer->normalize($markdown) === $markdown);     // true
var_dump($normalizer->normalize($fencedJson) === $fencedJson);  // true
echo $normalizer->normalize(' { "value": 42 } ');              // {"value":42}
```

Malformed JSON and JSON scalars such as `123`, `true` or `"hello"` are preserved in `UNKNOWN` and `JSON` modes. Those modes do not extract JSON embedded in prose or Markdown.

## Mixed Markdown instructions and JSON data

Use `TextFormat::MARKDOWN` for prompts containing Markdown instructions and fenced JSON data.
Only closed blocks explicitly labeled `json` containing a valid JSON object or array are
minified. Plain-text cleanup options are ignored in this mode, including `collapseSpaces`:
spaces inside JSON values, Markdown hard line breaks and other code blocks remain intact.

````php
use ByCerfrance\LlmApiLib\Normalization\TextFormat;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$prompt = <<<'MARKDOWN'
## Instructions
Use these data without changing their labels:
```json
{
    "label": "A  B",
    "amount": 1.2300
}
```
MARKDOWN;

echo (new TextNormalizer(minifyJson: true))->normalize($prompt, TextFormat::MARKDOWN);
````

The JSON body becomes `{"label":"A  B","amount":1.2300}`. Instructions, fence lines and
the spaces in `A  B` are preserved. Triple backticks and tilde fences are supported, including
longer fences, up to three leading spaces and CR/LF/CRLF line endings. A closing fence must
use the same character and be at least as long as its opener. The language label must be
exactly `json` (case-insensitive, ignoring surrounding spaces/tabs).

Other language blocks, inline code, indented code, invalid JSON and unclosed fences remain
unchanged. JSON scalars and unfenced JSON remain unchanged in Markdown mode as well.
For whole-document JSON use `TextFormat::JSON` or `UNKNOWN` instead.

To use this for image generation:

```php
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\TextFormat;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$normalizer = new PromptNormalizer(
    targets: [PromptTarget::IMAGE_PROMPT],
    textNormalizer: new TextNormalizer(minifyJson: true),
    textFormat: TextFormat::MARKDOWN,
);
```

Pass it as `promptNormalizer` to `ImageGenerator`. For chat messages, choose the relevant chat
targets and declare the same format, or return `MARKDOWN` from the per-content format resolver.

## Options

Every transformation is disabled by default. `new TextNormalizer()` preserves the input exactly.

| Option | Default | Effect |
| --- | --- | --- |
| `minifyJson` | `false` | Remove JSON whitespace outside strings in complete valid objects/arrays |
| `trimText` | `false` | Remove ASCII spaces, tabs and line endings at the document boundaries |
| `trimLineEnds` | `false` | Remove ASCII spaces and tabs at line ends |
| `collapseSpaces` | `false` | Replace each run of ASCII spaces/tabs with one space; preserve line endings |
| `maxConsecutiveBlankLines` | `null` | Limit empty or space/tab-only lines; `0` removes all blank lines, negative values are rejected |
| `normalizeLineEndings` | `false` | Convert CRLF and CR line endings to LF |

Only `minifyJson` applies to detected JSON. In `UNKNOWN`, `JSON` and `PLAIN_TEXT` modes, a recognized whole-document JSON object/array bypasses plain-text cleanup. If minification is disabled, that JSON remains byte-for-byte unchanged. `MARKDOWN` only inspects explicitly labeled fenced blocks.

Other transformations require `TextFormat::PLAIN_TEXT`. Their order is: line-ending conversion, horizontal-space compression, line-end trimming, blank-line limiting, then document-boundary trimming. Non-breaking spaces and other Unicode whitespace are preserved.

Normalization is idempotent: applying the same normalizer with the same format to its output produces the same text.

## Configure a chat

This variant assumes you already have a `$provider` and `$model` (see [Getting started](getting-started.md)). Calls through that provider use its usual transport.

```php
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Normalization\NormalizedChatResponse;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\ResponseNormalizer;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$textNormalizer = new TextNormalizer(minifyJson: true);
$chat = new Chat(
    provider: $provider,
    model: $model,
    promptNormalizer: new PromptNormalizer(
        targets: [PromptTarget::USER, PromptTarget::TOOL_RESULT],
        textNormalizer: $textNormalizer,
    ),
    responseNormalizer: new ResponseNormalizer(textNormalizer: $textNormalizer),
);

$response = $chat->chat(' { "question": "What is crop rotation?" } ');
echo $response->getText();
if ($response instanceof NormalizedChatResponse) {
    $original = $response->getOriginalResponse();
}
```

Only user messages and tool results are selected here. A JSON assistant answer is minified for display; ordinary prose is preserved. Omit `responseNormalizer` to keep output untouched, or omit `promptNormalizer` to normalize only output.

| Input target | Selected messages |
| --- | --- |
| `PromptTarget::SYSTEM` | System instructions |
| `PromptTarget::USER` | User messages |
| `PromptTarget::ASSISTANT_HISTORY` | Earlier assistant messages sent as context |
| `PromptTarget::TOOL_RESULT` | Tool result contents, preserving their call IDs |
| `PromptTarget::IMAGE_PROMPT` | The text prompt of an `ImageRequest` |
| `PromptTarget::IMAGE_REFERENCE` | Ordered reference images of an `ImageRequest` |

`PromptNormalizer` defaults to an empty target list. `Chat` defaults to `NullPromptNormalizer` and `NullResponseNormalizer`, which return the original objects unchanged.

Image-request targets do not select chat messages, and chat-message targets do not select
image-generation inputs. One normalizer can select both kinds explicitly.

Input normalization runs before **each provider turn**, including turns after tools execute. It transforms only the sent copy: the caller's request, returned history, and tool invocation registry retain original contents. Tool definitions, response schemas and generation options are preserved.

Output normalization changes `getText()` and the message returned by `getResult()`. Usage, provider metadata and tool calls are preserved. `getTurns()`, `toConversation()` and `reply()` use original results/history. `getOriginalResponse()` is available on `NormalizedChatResponse`; unchanged outputs retain their original response object.

### Declare formats per content

Use `textFormat: TextFormat::PLAIN_TEXT` only when every selected text part is known to be plain text. For mixed inputs, use a resolver. This variant identifies a specific text part owned by the application:

```php
use ByCerfrance\LlmApiLib\Chat\ChatRequest;
use ByCerfrance\LlmApiLib\Chat\Conversation\Conversation;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\MessageInterface;
use ByCerfrance\LlmApiLib\Chat\Conversation\Message\UserMessage;
use ByCerfrance\LlmApiLib\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\TextFormat;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$prose = new TextContent('  Please   explain this code.  ');
$code = new TextContent("if ready:\n    print('a  b')\n");
$request = new ChatRequest(new Conversation([
    new UserMessage(new ArrayContent($prose, $code)),
]));
$normalizer = new PromptNormalizer(
    targets: [PromptTarget::USER],
    textNormalizer: new TextNormalizer(trimText: true, collapseSpaces: true),
    textFormatResolver: static fn(MessageInterface $message, TextContent $part): TextFormat =>
        $part === $prose ? TextFormat::PLAIN_TEXT : TextFormat::UNKNOWN,
);
$normalized = $normalizer->normalize($request);
```

The prose becomes `Please explain this code.`; the code part and the original request are unchanged. The resolver overrides `textFormat` and is also supported by `ResponseNormalizer`. Keep resolver decisions stable when reapplying normalization.

### Content boundaries

- Multipart messages are traversed part by part.
- `JsonContent` is already serialized compactly and remains unchanged.
- Images remain unchanged unless an `ImageNormalizerInterface` implementation is supplied through `imageNormalizer`.
- Other content types and custom message subclasses remain unchanged to retain application-specific metadata.
- Native provider assistant messages remain entirely unchanged in input, including visible text, because continuation may depend on signed native parts. Their displayed output can be normalized; native metadata and original history remain accessible.
- Normalizer exceptions propagate; failed input normalization prevents that provider turn from running.

Offline chat example: [examples/normalization.php](../examples/normalization.php).

`StreamingChat` supports the same constructor options. Deltas remain untouched; only the final
assembled response is normalized, so its text can differ from concatenated deltas.
See the [streaming example](streaming.md#normalize-inputs-and-the-assembled-answer).

## Resize and convert images

Install **either** `ext-imagick` or `ext-gd` to process images. Neither is a mandatory runtime
dependency of this library. Construction does not require an extension; calling `normalize()`
throws `RuntimeException` if the selected backend is unavailable.

`ImageNormalizer` selects Imagick first, then GD. To force a backend, use
`ImagickImageNormalizer` or `GdImageNormalizer` with the same options. Selection depends on
extension availability, not on the individual image: processing failures do not silently
fall back to another backend. Encoded bytes can differ between backends and builds.

This offline variant uses a local `photo.jpg` and the autoloader from the first example:

```php
use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizer;

$original = new ImageContent(ImageAsset::fromLocalFile(__DIR__ . '/photo.jpg', 'image/jpeg'));
$normalizer = new ImageNormalizer(
    maxWidth: 1024,
    maxHeight: 1024,
    quality: 80,
    format: 'webp',
);
$normalized = $normalizer->normalize($original);
echo $normalized->image->mimeType . PHP_EOL;
echo strlen($normalized->image->getBytes()) . ' output bytes' . PHP_EOL;
```

A 2400 × 1600 image becomes 1024 × 682 (integer dimensions are rounded down), with its
aspect ratio retained up to pixel rounding. Smaller images are never enlarged. The local
file remains unchanged; transformed output is embedded image data.

| Option | Default | Effect |
| --- | --- | --- |
| `maxWidth` | `null` | Maximum width in pixels; positive integer or no width limit |
| `maxHeight` | `null` | Maximum height in pixels; positive integer or no height limit |
| `quality` | `null` | JPEG/WebP quality from 0 to 100; when encoding is needed, null uses 85 |
| `format` | `null` | `jpeg`, `png` or `webp`; null preserves the input format |

PNG stays lossless; `quality` has no effect on PNG. JPEG/WebP encoding can lose details.
Transparency is retained for PNG/WebP; JPEG conversion uses a white background.
EXIF orientation is applied to pixels before transformations; output metadata is removed.
When no transformation is needed, the original content is returned unchanged.

Supported inputs are static JPEG, PNG and WebP in `ImageAsset` (inline bytes, local file,
or data URL), `ImageContent` wrapping an asset, and `ImageUrlContent` (base64 data URL).
Assets are accepted directly, so the same backend can process references from an `ImageRequest`.
The input representation and image `detail`
setting are retained. Custom image content subclasses are returned unchanged to preserve their
application-specific representation. Remote URLs are returned unchanged without being downloaded.
Invalid data, unsupported formats such as GIF, and animated PNG/WebP cause a
`RuntimeException`; animations are never silently reduced to one frame.

Repeated normalization of the same input object reuses its result. The same normalizer
instance also recognizes its own output objects and avoids recompressing them. This uses
weak object keys, not a global cache. Reconstructing an image object or using another
normalizer instance may encode again; lossy image processing is not byte-idempotent across
serialization or instances. Cached local-file inputs represent the first processed snapshot;
create a new image content object to process a changed file.

Runnable offline example: `php examples/image-normalization.php`
([script](../examples/image-normalization.php)). It accepts an optional image path and a backend
name (`auto`, `gd`, `imagick`), and prints dimensions and byte counts without saving files.

### Normalize images sent to a model

This variant assumes a configured `$provider` and `$model`:

```php
use ByCerfrance\LlmApiLib\Chat\Chat;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;

$chat = new Chat(
    $provider,
    $model,
    promptNormalizer: new PromptNormalizer(
        targets: [PromptTarget::USER],
        imageNormalizer: new ImageNormalizer(maxWidth: 1024, maxHeight: 1024),
    ),
);
```

Use `ResponseNormalizer(imageNormalizer: ...)` to transform displayed output images.
Original response/history images remain available. Reducing image dimensions can reduce
model processing costs, depending on the provider; reducing encoded byte size alone does
not guarantee fewer image tokens, and conversion does not guarantee a smaller file.

## Normalize standalone image requests

`ImageGenerator` accepts the same `PromptNormalizerInterface` as `Chat` and `StreamingChat`.
It defaults to `NullPromptNormalizer`. A string passed to `generate()` is converted to an
`ImageRequest` before normalization. The normalized copy is sent to the provider; the original
prompt, reference assets and local files remain unchanged. Reference order, image count,
generation options and aspect ratio are preserved.

This variant assumes a configured image `$provider` and `$model`:

```php
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageGenerator;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptNormalizer;
use ByCerfrance\LlmApiLib\Normalization\PromptTarget;
use ByCerfrance\LlmApiLib\Normalization\TextFormat;
use ByCerfrance\LlmApiLib\Normalization\TextNormalizer;

$normalizer = new PromptNormalizer(
    targets: [PromptTarget::IMAGE_PROMPT, PromptTarget::IMAGE_REFERENCE],
    textNormalizer: new TextNormalizer(trimText: true, collapseSpaces: true),
    imageNormalizer: new ImageNormalizer(maxWidth: 1024, maxHeight: 1024),
    textFormat: TextFormat::PLAIN_TEXT,
);
$generator = new ImageGenerator($provider, $model, promptNormalizer: $normalizer);
$request = new ImageRequest(
    '  Draw   a farm using this reference.  ',
    images: [ImageAsset::fromLocalFile(__DIR__ . '/reference.png', 'image/png')],
    aspectRatio: '1:1',
);
$response = $generator->generate($request);
```

This sends `Draw a farm using this reference.` with resized reference data when needed.
Generated output images are not processed by this input pipeline. `textFormat` declares the
image prompt's format; `textFormatResolver` applies only to chat message contents. Normalizer
errors, invalid transformed prompts and invalid reference assets fail before the provider call.

For manual asset processing, call `$normalizer = new ImageNormalizer(maxWidth: 1024)` and
`$normalizedAsset = $normalizer->normalize($asset)`. The result is an `ImageAsset`, not a
chat content wrapper. Reusing that asset inside `ImageContent` shares the same backend cache.

Offline example: [examples/image-request-normalization.php](../examples/image-request-normalization.php).

## Supply your own rules

Implement `TextNormalizerInterface::normalize(string $text, TextFormat $format = TextFormat::UNKNOWN): string` to supply another text normalizer. Preserve the format guarantees defined by the interface.

Implement `PromptNormalizerInterface` or `ResponseNormalizerInterface` for custom request/response handling. The built-in normalizers compose text and image normalizers without requiring a shared `mixed` contract.

### Updating custom normalizers from the initial 2.x implementation

`PromptNormalizerInterface::normalize()` now accepts and returns
`ChatRequestInterface|ImageRequestInterface`. Custom implementations must accept both kinds and
return the same request kind; an unsupported kind can simply be returned unchanged.
`ImageNormalizerInterface::normalize()` now accepts and returns
`ImageAsset|ImageContent|ImageUrlContent`, preserving the supplied representation.
Update custom method signatures accordingly. Built-in normalizers keep existing chat calls working.

Related: [Conversations](conversations.md), [Usage and pricing](usage-and-pricing.md).
