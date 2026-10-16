<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat\Conversation\Message;

use ByCerfrance\LlmApiLib\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Content\NullContent;
use ByCerfrance\LlmApiLib\Content\TextContent;
use Override;

readonly class Message implements MessageInterface
{
    private ContentInterface $content;

    public function __construct(
        string|ContentInterface|null $content,
        private RoleEnum $role = RoleEnum::USER,
    ) {
        $this->content = match (get_debug_type($content)) {
            'string' => new TextContent($content),
            'null' => new NullContent(),
            default => $content,
        };
    }

    #[Override]
    public function getRole(): RoleEnum
    {
        return $this->role;
    }

    #[Override]
    public function getContent(): ContentInterface
    {
        return $this->content;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'content' => $this->content,
            'role' => $this->role,
        ];
    }

    #[Override]
    public function requiredCapabilities(): array
    {
        return $this->content->requiredCapabilities();
    }
}
