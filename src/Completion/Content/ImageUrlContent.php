<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ByCerfrance\LlmApiLib\Model\Capability;
use GdImage;
use Override;
use Psr\Http\Message\UriInterface;
use RuntimeException;

readonly class ImageUrlContent implements ContentInterface
{
    use FileContentTrait;

    private static function gdImageToBase64(
        GdImage $image,
        ?int $maxSize = null,
        string $format = 'jpeg',
        int $quality = -1,
    ): string {
        if (false === extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required to create an `ImageUrlContent` from a `GdImage`');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'llm_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temp file.');
        }

        try {
            // Reduce resolution to prevent http body overload
            if (null !== $maxSize) {
                $image = b_img_resize($image, $maxSize, $maxSize);
            }

            // Write JPEG binary to disk (filename string → PHP won't close any stream we need)
            $result = match ($format) {
                'gif' => imagegif($image, $tmp),
                'jpg', 'jpeg' => imagejpeg($image, $tmp, $quality),
                'png' => imagepng($image, $tmp, $quality),
                'webp' => imagewebp($image, $tmp, $quality),
                default => throw new RuntimeException(sprintf('Unsupported image format "%s"', $format)),
            };
            if (false === $result) {
                throw new RuntimeException('Image creation failed');
            }

            return self::fileToBase64($tmp);
        } finally {
            // Always cleanup the binary temp file
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public static function fromGdImage(
        GdImage $image,
        ?string $detail = null,
        ?int $maxSize = null,
        string $format = 'jpeg',
        int $quality = -1,
    ): self {
        return new self(
            url: sprintf(
                'data:%s;base64,%s',
                match ($format) {
                    'gif' => 'image/gif',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                },
                self::gdImageToBase64($image, $maxSize, $format, $quality),
            ),
            detail: $detail,
        );
    }

    public static function fromFile(
        mixed $file,
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
            detail: $detail,
        );
    }

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

    #[Override]
    public function requiredCapabilities(): array
    {
        return [
            Capability::IMAGE,
            Capability::OCR,
        ];
    }
}
