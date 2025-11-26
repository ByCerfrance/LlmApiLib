<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Message;

use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use Override;

readonly class Message implements MessageInterface
{
    private ContentInterface $content;

    public function __construct(
        string|ContentInterface $content,
        private RoleEnum $role = RoleEnum::USER,
    ) {
        if (is_string($content)) {
            $content = new TextContent($content);
        }

        $this->content = $content;
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
