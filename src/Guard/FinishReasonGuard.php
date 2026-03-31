<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Guard;

use ByCerfrance\LlmApiLib\Completion\CompletionResponseInterface;
use ByCerfrance\LlmApiLib\Completion\FinishReason;
use ByCerfrance\LlmApiLib\LlmInterface;
use RuntimeException;

/**
 * Guard that rejects LLM responses with specific finish reasons.
 *
 * By default, rejects responses that were truncated (LENGTH) or filtered (CONTENT_FILTER).
 *
 * @example new FinishReasonGuard($provider) // rejects LENGTH and CONTENT_FILTER
 * @example new FinishReasonGuard($provider, FinishReason::LENGTH) // rejects LENGTH only
 */
readonly class FinishReasonGuard extends Guard
{
    /**
     * @param LlmInterface $provider The inner LLM provider to decorate
     * @param FinishReason ...$rejected Finish reasons to reject (defaults to LENGTH, CONTENT_FILTER)
     */
    public function __construct(
        LlmInterface $provider,
        FinishReason ...$rejected,
    ) {
        $rejected = $rejected ?: [FinishReason::LENGTH, FinishReason::CONTENT_FILTER];

        parent::__construct(
            $provider,
            static function (CompletionResponseInterface $response) use ($rejected): void {
                $finishReason = $response->getFinishReason();

                if (null !== $finishReason && in_array($finishReason, $rejected, true)) {
                    throw new RuntimeException(
                        sprintf('LLM response rejected: finish_reason is "%s"', $finishReason->value)
                    );
                }
            },
        );
    }
}
