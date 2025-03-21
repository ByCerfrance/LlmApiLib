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

$completions = $llm->chat('Salut !');
print $completions->getLastMessage()->getContent(); // "Salut ! Comment allez-vous ?"

$completions = $llm->chat($completions->withNewMessage('Bien merci et toi ?'));
print $completions->getLastMessage()->getContent(); // "Bien, merci. Comment puis-je vous aider ?"

// ...
```

#### With instructions

```php
use ByCerfrance\LlmApiLib\Completions\Completions;
use ByCerfrance\LlmApiLib\Completions\Message\Message;
use ByCerfrance\LlmApiLib\Completions\Message\RoleEnum;
use ByCerfrance\LlmApiLib\Llm;

// Instructions
$completions = new Completions(new Message(
    'Tu es un assistant comptable, présentes toi comme tel.',
    role: RoleEnum::SYSTEM,
));

$llm = new Llm();

$completions = $llm->chat($completions->withNewMessage('Salut !'));
print $completions->getLastMessage()->getContent(); // "Bonjour, je suis votre assistant comptable. Comment puis-je vous aider ?"

// ...
```


