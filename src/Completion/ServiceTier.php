<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion;

/**
 * Service tier for the API request.
 *
 * Controls the processing priority and pricing tier.
 *
 * @see https://platform.openai.com/docs/api-reference/chat/create#chat-create-service_tier
 */
enum ServiceTier: string
{
    case AUTO = 'auto';
    case DEFAULT = 'default';
    case FLEX = 'flex';
    case PRIORITY = 'priority';

    /**
     * Get the fallback tier.
     *
     * @return self|null
     */
    public function fallback(): ?self
    {
        return match ($this) {
            self::PRIORITY => self::AUTO,
            self::FLEX => self::AUTO,
            self::AUTO => self::DEFAULT,
            self::DEFAULT => null,
        };
    }
}
