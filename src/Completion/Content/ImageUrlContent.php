<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use Override;
use Psr\Http\Message\UriInterface;

readonly class ImageUrlContent implements ContentInterface
{
    public function __construct(
        private UriInterface|string $url,
        private ?string $detail = null,
    ) {
    }

    /**
     * Get URL.
     *
     * @return UriInterface|string
     */
    public function getUrl(): UriInterface|string
    {
        return $this->url;
    }

    /**
     * Get detail.
     *
     * @return string|null
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }

    #[Override]
    public function jsonSerialize(bool $encapsulated = false): array
    {
        return [
            'type' => 'image_url',
            'image_url' => array_filter([
                'url' => (string)$this->url,
                'detail' => $this->detail,
            ]),
        ];
    }
}
