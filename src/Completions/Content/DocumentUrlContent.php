<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completions\Content;

use Psr\Http\Message\UriInterface;

readonly class DocumentUrlContent implements ContentInterface
{
    public function __construct(
        private UriInterface|string $url,
        private ?string $name = null,
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
     * Get name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
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

    /**
     * @param bool $encapsulated *
     *
     * @inheritDoc
     */
    public function jsonSerialize(bool $encapsulated = false): array
    {
        return array_filter([
            'type' => 'document_url',
            'document_url' => array_filter([
                'url' => (string)$this->url,
                'detail' => $this->detail,
            ]),
            'document_name' => $this->name,
        ]);
    }
}
