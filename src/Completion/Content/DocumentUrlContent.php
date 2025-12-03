<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ByCerfrance\LlmApiLib\Model\Capability;
use Override;
use Psr\Http\Message\UriInterface;
use RuntimeException;

readonly class DocumentUrlContent implements ContentInterface
{
    use FileContentTrait;

    public static function fromFile(
        mixed $file,
        ?string $name = null,
        ?string $detail = null,
    ): self {
        $mime = 'application/octet-stream';
        $base64Content = match ($debugType = get_debug_type($file)) {
            'string' => self::fileToBase64($file, $mime),
            'resource (stream)' => self::streamToBase64($file, $mime),
            default => throw new RuntimeException(sprintf('Unable to create a content from a %s', $debugType)),
        };

        return new self(
            url: sprintf('data:%s;base64,%s', $mime, $base64Content),
            name: $name,
            detail: $detail,
        );
    }

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

    #[Override]
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

    #[Override]
    public function requiredCapabilities(): array
    {
        return [
            Capability::DOCUMENT,
            Capability::OCR,
        ];
    }
}
