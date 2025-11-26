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
