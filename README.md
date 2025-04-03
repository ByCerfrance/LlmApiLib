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
