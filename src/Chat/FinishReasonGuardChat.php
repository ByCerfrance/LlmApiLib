<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Chat;

use ByCerfrance\LlmApiLib\Completion\FinishReason;
use RuntimeException;

readonly class FinishReasonGuardChat extends GuardChat
{
    public function __construct(ChatInterface $chat, FinishReason ...$rejected)
    {
        $rejected = $rejected ?: [FinishReason::LENGTH, FinishReason::CONTENT_FILTER];
        parent::__construct($chat, static function (ChatResponseInterface $response) use ($rejected): void {
            if (in_array($response->getResult()->getFinishReason(), $rejected, true)) {
                throw new RuntimeException('Rejected finish reason.');
            }
        });
    }
}
