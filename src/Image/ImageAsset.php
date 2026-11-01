<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Image;

use InvalidArgumentException;
use RuntimeException;

readonly class ImageAsset
{
    /** Create inline image data without interpreting the bytes or fetching a URL. */
    public static function fromBytes(string $bytes, string $mimeType): self
    {
        return new self($mimeType, base64_encode($bytes));
    }

    /**
     * Retain a local regular file path; its contents are read only when requested.
     * Stream wrappers and remote URLs are not accepted.
     *
     * @throws InvalidArgumentException For an invalid image MIME type.
     * @throws RuntimeException When the local file cannot be read.
     */
    public static function fromLocalFile(string $path, string $mimeType): self
    {
        $local = realpath($path);
        if ($local === false || !is_file($local) || !is_readable($local)) {
            throw new RuntimeException('Expected a readable local image file.');
        }
        return new self($mimeType, localPath: $local);
    }

    public function __construct(
        public ?string $mimeType,
        public ?string $base64 = null,
        public ?string $url = null,
        public ?string $localPath = null,
    ) {
        if (count(array_filter([$base64, $url, $localPath], static fn(?string $value): bool => $value !== null)) !== 1
            || ($mimeType !== null && !str_starts_with($mimeType, 'image/'))
            || ($base64 !== null && ($base64 === '' || $mimeType === null || base64_decode($base64, true) === false))
            || ($localPath !== null && ($mimeType === null || !preg_match('~^image/[a-zA-Z0-9][a-zA-Z0-9.+-]*$~D', $mimeType)
                || realpath($localPath) !== $localPath || !is_file($localPath) || !is_readable($localPath)))
            || ($url !== null && $url === '')) {
            throw new InvalidArgumentException('An image requires valid base64 data, an absolute local file path with an image MIME type, or a URL.');
        }
    }

    /** @throws RuntimeException When a local file is missing, unreadable or empty at read time. */
    public function getBytes(): ?string
    {
        if ($this->localPath !== null) {
            clearstatcache(true, $this->localPath);
            if (!is_file($this->localPath) || !is_readable($this->localPath)) {
                throw new RuntimeException('Expected a readable local image file.');
            }
            $bytes = @file_get_contents($this->localPath);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Unable to read non-empty local image data.');
            }

            return $bytes;
        }

        return $this->base64 === null ? null : base64_decode($this->base64, true);
    }

    /** @throws RuntimeException When a local file is missing, unreadable or empty at read time. */
    public function getBase64(): ?string
    {
        return $this->localPath === null ? $this->base64 : base64_encode($this->getBytes());
    }
}
