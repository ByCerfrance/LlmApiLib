<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use InvalidArgumentException;

readonly class ContentFactory
{
    /**
     * Create content.
     *
     * @param mixed $content
     *
     * @return ContentInterface
     */
    public static function create(mixed $content): ContentInterface
    {
        return match (true) {
            is_string($content) => self::createFromString($content),
            is_array($content) => self::createFromArray($content),
            default => new InvalidArgumentException('Not supported content type format'),
        };
    }

    /**
     * Create content from array.
     *
     * @param array $content
     *
     * @return ContentInterface
     */
    public static function createFromArray(array $content): ContentInterface
    {
        return match ($content['type'] ?? null) {
            'document_url' => new DocumentUrlContent(
                url: $content['document_url']['url'] ??
                $content['document_url'] ??
                throw new InvalidArgumentException('Invalid document url content'),
                name: $content['document_name'] ?? null,
                detail: $content['document_url']['detail'] ?? null,
            ),
            'image_url' => new ImageUrlContent(
                url: $content['image_url']['url'] ??
                $content['image_url'] ??
                throw new InvalidArgumentException('Invalid image url content'),
                detail: $content['image_url']['detail'] ?? null,
            ),
            'text' => self::createFromString($content['text'] ?? ''),
            default => new InvalidArgumentException(
                sprintf(
                    'Not supported content type "%s"',
                    $content['type'] ?? null,
                )
            ),
        };
    }

    /**
     * Create content from string.
     *
     * @param string $content
     *
     * @return ContentInterface
     */
    public static function createFromString(string $content): ContentInterface
    {
        return new TextContent($content);
    }
}
