<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use InvalidArgumentException;
use Override;
use RuntimeException;
use WeakMap;

/** Shared image policy and per-instance cache for immutable contents. */
abstract class AbstractImageNormalizer implements ImageNormalizerInterface
{
    /** @var WeakMap<ImageAsset|ImageContent|ImageUrlContent, ImageAsset|ImageContent|ImageUrlContent> */
    private WeakMap $results;
    /** @var WeakMap<ImageAsset|ImageContent|ImageUrlContent, bool> */
    private WeakMap $normalized;

    /**
     * @param int|null $maxWidth Maximum pixel width; images are never enlarged.
     * @param int|null $maxHeight Maximum pixel height; aspect ratio is preserved.
     * @param int|null $quality JPEG/WebP quality from 0 to 100; null uses 85 when encoding is needed.
     * @param string|null $format Output format: jpeg, png or webp; null preserves the input format.
     * @throws InvalidArgumentException When dimensions, quality or output format are invalid.
     */
    public function __construct(
        protected readonly ?int $maxWidth = null,
        protected readonly ?int $maxHeight = null,
        protected readonly ?int $quality = null,
        protected readonly ?string $format = null,
    ) {
        if (($maxWidth !== null && $maxWidth < 1) || ($maxHeight !== null && $maxHeight < 1)) {
            throw new InvalidArgumentException('Maximum image dimensions must be positive.');
        }
        if ($quality !== null && ($quality < 0 || $quality > 100)) {
            throw new InvalidArgumentException('Image quality must be between 0 and 100.');
        }
        if ($format !== null && !in_array($format, ['jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('Image output format must be jpeg, png or webp.');
        }
        $this->results = new WeakMap();
        $this->normalized = new WeakMap();
    }

    #[Override]
    public function normalize(ImageAsset|ImageContent|ImageUrlContent $image): ImageAsset|ImageContent|ImageUrlContent
    {
        $extension = $this->getExtensionName();
        if (!extension_loaded($extension)) {
            throw new RuntimeException(sprintf('The %s extension is required to use %s.', $extension, static::class));
        }
        if (!in_array($image::class, [ImageAsset::class, ImageContent::class, ImageUrlContent::class], true)) {
            return $image;
        }
        if (isset($this->normalized[$image])) {
            return $image;
        }
        if (isset($this->results[$image])) {
            return $this->results[$image];
        }
        if ($image instanceof ImageContent) {
            $asset = $this->normalize($image->image);

            return $asset === $image->image ? $image : $this->remember($image, new ImageContent($asset));
        }
        $bytes = $this->getBytes($image);
        if ($bytes === null) {
            return $image;
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new RuntimeException('Unable to read valid image dimensions.');
        }
        $sourceFormat = match ($info['mime']) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException(sprintf('Unsupported image type "%s"; expected JPEG, PNG or WebP.', $info['mime'])),
        };
        $this->rejectAnimation($bytes, $sourceFormat);
        $orientation = ImageOrientation::read($bytes, $sourceFormat);
        [$sourceWidth, $sourceHeight] = $orientation >= 5 ? [$info[1], $info[0]] : [$info[0], $info[1]];
        $format = $this->format ?? $sourceFormat;
        $scale = min(1, ($this->maxWidth ?? $sourceWidth) / $sourceWidth, ($this->maxHeight ?? $sourceHeight) / $sourceHeight);
        $width = max(1, (int) floor($sourceWidth * $scale));
        $height = max(1, (int) floor($sourceHeight * $scale));
        if ($width === $sourceWidth && $height === $sourceHeight && $format === $sourceFormat && ($this->quality === null || $format === 'png')) {
            return $image;
        }
        $output = $this->encode($bytes, $width, $height, $format, $orientation);
        if ($output === '') {
            throw new RuntimeException('Image normalization produced empty data.');
        }
        $mime = 'image/' . $format;
        $result = $image instanceof ImageAsset
            ? ImageAsset::fromBytes($output, $mime)
            : new ImageUrlContent(sprintf('data:%s;base64,%s', $mime, base64_encode($output)), $image->getDetail());

        return $this->remember($image, $result);
    }

    /**
     * @template T of ImageAsset|ImageContent|ImageUrlContent
     * @param T $image
     * @param T $result
     * @return T
     */
    private function remember(ImageAsset|ImageContent|ImageUrlContent $image, ImageAsset|ImageContent|ImageUrlContent $result): ImageAsset|ImageContent|ImageUrlContent
    {
        $this->results[$image] = $result;
        $this->normalized[$result] = true;

        return $result;
    }

    abstract protected function getExtensionName(): string;

    /** @throws RuntimeException When decoding, resizing or encoding fails. */
    abstract protected function encode(string $bytes, int $width, int $height, string $format, int $orientation): string;

    private function getBytes(ImageAsset|ImageUrlContent $image): ?string
    {
        if ($image instanceof ImageAsset) {
            if ($image->url === null) {
                return $image->getBytes();
            }
            $url = $image->url;
        } else {
            $url = (string) $image->getUrl();
        }
        if (strncasecmp($url, 'data:', 5) !== 0) {
            return null;
        }
        if (!preg_match('~^data:image/[a-z0-9.+-]+;base64,(.*)$~isD', $url, $matches)) {
            throw new RuntimeException('Expected a base64 image data URL.');
        }
        $bytes = base64_decode($matches[1], true);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Invalid or empty base64 image data.');
        }

        return $bytes;
    }

    /** Reject animation before a backend can silently flatten it. */
    private function rejectAnimation(string $bytes, string $format): void
    {
        if ($format === 'jpeg') {
            return;
        }
        $offset = $format === 'png' ? 8 : 12;
        $length = strlen($bytes);
        while ($offset + 8 <= $length) {
            if ($format === 'png') {
                $size = unpack('Nsize', substr($bytes, $offset, 4))['size'];
                $type = substr($bytes, $offset + 4, 4);
                $chunkLength = $size + 12;
            } else {
                $type = substr($bytes, $offset, 4);
                $size = unpack('Vsize', substr($bytes, $offset + 4, 4))['size'];
                $chunkLength = $size + 8 + ($size % 2);
            }
            if ($type === 'acTL' || $type === 'ANIM' || $type === 'ANMF') {
                throw new RuntimeException('Animated images cannot be normalized without losing frames.');
            }
            $offset += $chunkLength;
        }
    }
}
