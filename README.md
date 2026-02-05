# LLM API Library

## Installation

You can install library with [Composer](https://getcomposer.org/), it's the recommended installation.

1. Edit your `composer.json` file and add this lines:

```json
{
    ...
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.packagist.com/vigicorp/bycerfrance/"
        }
    ],
    ...
}
```

2. Use the command to add library:

```bash
$ composer require bycerfrance/llm-api-lib
```

## Usage

### Providers

- OVH
- Mistral

### Chat

#### Basic usage

```php
$llm = new \ByCerfrance\LlmApiLib\Llm();

$completion = $llm->chat('Salut !');
print $completion->getLastMessage()->getContent(); // "Salut ! Comment allez-vous ?"

$completion = $llm->chat($completion->withNewMessage('Bien merci et toi ?'));
print $completion->getLastMessage()->getContent(); // "Bien, merci. Comment puis-je vous aider ?"

// ...
```

#### With instructions

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Completion\Message\Message;
use ByCerfrance\LlmApiLib\Completion\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Llm;

// Instructions
$completion = new Completion(new Message(
    'Tu es un assistant comptable, présentes toi comme tel.',
    role: RoleEnum::SYSTEM,
));

$llm = new Llm();

$completion = $llm->chat($completion->withNewMessage('Salut !'));
print $completion->getLastMessage()->getContent(); // "Bonjour, je suis votre assistant comptable. Comment puis-je vous aider ?"

// ...
```

#### Content types

#### ArrayContent

Allows combining multiple contents (`ContentInterface` or strings) into a single object. Useful for sending multiple
elements in a single message.

Example:

```php
$content = new ArrayContent(
    new TextContent('First message'),
    'Second message'
);
```

#### DocumentUrlContent

Represents a document accessible via a URL. Supports capabilities `document` and `ocr`.

Example:

```php
$content = new DocumentUrlContent('https://example.com/document.pdf');
```

Creates a `DocumentUrlContent` instance from a local file path or stream. The file is automatically converted to a
base64-encoded data URL.

Example:

```php
$content = DocumentUrlContent::fromFile('/path/to/document.pdf', 'custom-name.pdf');
```

Parameters:

- `$file`: Path to the file as a string or a stream resource.
- `$name`: Optional custom name for the document.
- `$detail`: Optional detail level for processing (e.g., 'auto', 'low', 'high').

#### ImageUrlContent

Represents an image accessible via a URL. Supports capabilities `image` and `ocr`.

Example:

```php
$content = new ImageUrlContent('https://example.com/image.jpg');
```

Creates an `ImageUrlContent` instance from a GD image resource. The image is automatically converted to a base64-encoded
data URL.

Example:

```php
$content = ImageUrlContent::fromGdImage($gdImage, 'high');
```

Parameters:

- `$image`: A GD image resource.
- `$detail`: Optional detail level for processing (e.g., 'auto', 'low', 'high').
- `$maxSize`: Optional maximum size for resizing the image.
- `$format`: Optional image format ('jpeg', 'png', 'gif', 'webp').
- `$quality`: Optional quality setting for JPEG/PNG/WebP formats.

Creates an `ImageUrlContent` instance from a local file path or stream. The file is automatically converted to a
base64-encoded data URL.

Example:

```php
$content = ImageUrlContent::fromFile('/path/to/image.png', 'low');
```

Parameters:

- `$file`: Path to the file as a string or a stream resource.
- `$detail`: Optional detail level for processing (e.g., 'auto', 'low', 'high').

#### InputAudioContent

Represents audio content encoded in base64 with a specified format. Supports capability `audio`.

Example:

```php
$content = new InputAudioContent('base64encodeddata', 'wav');
```

#### TextContent & JsonContent

`TextContent` represents plain text or text read from a file. It supports the `text` capability.

`JsonContent` represents structured data in JSON format. It also supports the `text` capability.

Examples:

```php
$text = new TextContent('Hello, world!');
$json = new JsonContent(['key' => 'value']);
```

When creating a `TextContent` instance, you can pass an associative array of placeholders that will be applied to the
content using `str_replace`. This allows dynamic content generation based on placeholders in the text.

Example:

```php
$content = new TextContent('Hello {name}, you are {age} years old.', ['name' => 'John', 'age' => 30]);
echo $content; // Outputs: "Hello John, you are 30 years old."
```

The placeholders are applied using the format `{key}` where `key` corresponds to the keys in the placeholder array.

Creates a `TextContent` instance from a local file path or stream. The file content is automatically loaded and can be
processed with optional placeholders.

Example:

```php
$content = TextContent::fromFile('/path/to/text.txt', ['name' => 'John', 'age' => 30]);
```

Parameters:

- `$file`: Path to the file as a string or a stream resource.
- `$placeholders`: Optional associative array of placeholders to apply to the content.

### Tools (Function Calling)

Tools allow the LLM to call external functions during inference. The library handles the tool execution loop
automatically.

#### Defining a tool

```php
use ByCerfrance\LlmApiLib\Completion\Tool\Tool;

$weatherTool = new Tool(
    name: 'get_weather',
    description: 'Get the current weather for a location',
    parameters: [
        'type' => 'object',
        'properties' => [
            'location' => [
                'type' => 'string',
                'description' => 'The city name',
            ],
        ],
        'required' => ['location'],
    ],
    callback: function (array $arguments): array {
        // Your logic here
        return [
            'temperature' => 20,
            'unit' => 'celsius',
            'condition' => 'sunny',
        ];
    },
);
```

#### Using tools in a completion

```php
use ByCerfrance\LlmApiLib\Completion\Completion;
use ByCerfrance\LlmApiLib\Llm;

$completion = (new Completion(['Quel temps fait-il à Paris ?']))
    ->withTools($weatherTool)
    ->withMaxToolIterations(5); // Optional, default is 10

$llm = new Llm();
$response = $llm->chat($completion);

print $response->getLastMessage()->getContent();
// "Il fait actuellement 20°C à Paris avec un temps ensoleillé."
```

#### Multiple tools

```php
use ByCerfrance\LlmApiLib\Completion\Tool\ToolCollection;

$completion = (new Completion(['...']))
    ->withTools($weatherTool, $calculatorTool, $searchTool);

// Or using a collection
$tools = new ToolCollection($weatherTool, $calculatorTool);
$completion = (new Completion(['...']))->withTools($tools);
```

The library automatically:
- Sends tools definition to the LLM
- Detects when the LLM wants to call a tool
- Executes the callback with the provided arguments
- Sends the result back to the LLM
- Continues until the LLM provides a final response or max iterations is reached

### Usage

You can retrieve tokens usage of LLM with method `LlmInterface::getUsage(): UsageInterface`.

### Capabilities

This library supports a wide range of LLM capabilities, allowing developers to leverage advanced features such as
multimodal processing, structured output, and reasoning. The following table lists the supported capabilities along with
their descriptions.

| Capability      | Description (English)                                                                                                       |
|-----------------|-----------------------------------------------------------------------------------------------------------------------------|
| **text**        | Ability to read, process, and generate natural language text.                                                               |
| **image**       | Ability to interpret visual content from images.                                                                            |
| **ocr**         | Ability to extract textual content embedded within images (printed or handwritten).                                         |
| **document**    | Ability to process structured, often multi-page documents (e.g., PDFs), including visual layout and textual interpretation. |
| **audio**       | Ability to process and interpret speech or audio signals.                                                                   |
| **video**       | Ability to understand and analyze visual-temporal content from videos.                                                      |
| **reasoning**   | Ability to perform logical, analytical, or multi-step reasoning to derive conclusions.                                      |
| **json_output** | Ability to generate responses strictly formatted as valid JSON.                                                             |
| **json_schema** | Ability to generate responses that strictly follow a predefined JSON schema.                                                |
| **code**        | Ability to interpret, generate, or transform programming code.                                                              |
| **tools**       | Ability to call external tools or functions during inference.                                                               |
| **multimodal**  | Ability to combine and reason across multiple input types (e.g., text + image + audio + video).                             |

Each provider implementing the `LlmInterface` must declare its supported capabilities via the `getCapabilities()`
method. The `Llm` class automatically filters providers based on compatibility with the requested capabilities, ensuring
that only suitable providers are used for each request.
