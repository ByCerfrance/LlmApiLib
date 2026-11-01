<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use Override;
use RuntimeException;

class GdImageNormalizer extends AbstractImageNormalizer
{
    #[Override]
    protected function getExtensionName(): string
    {
        return 'gd';
    }

    #[Override]
    protected function encode(string $bytes, int $width, int $height, string $format, int $orientation): string
    {
        $encoder = match ($format) {
            'jpeg' => 'imagejpeg',
            'png' => 'imagepng',
            'webp' => 'imagewebp',
        };
        if (!function_exists($encoder)) {
            throw new RuntimeException(sprintf('The GD build does not support %s output.', $format));
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new RuntimeException('GD could not decode the image.');
        }
        $target = null;
        try {
            if ($orientation >= 3 && $orientation !== 4) {
                $angle = match ($orientation) { 3 => 180, 8 => 90, default => -90 };
                $rotated = imagerotate($source, $angle, imagecolorallocatealpha($source, 0, 0, 0, 127));
                if ($rotated === false) {
                    throw new RuntimeException('GD could not orient the image.');
                }
                $source = $rotated;
            }
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($source, in_array($orientation, [2, 5], true) ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
            }
            $target = imagecreatetruecolor($width, $height);
            if ($target === false) {
                throw new RuntimeException('GD could not allocate the output image.');
            }
            if ($format === 'jpeg') {
                imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            } else {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            }
            if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source))) {
                throw new RuntimeException('GD could not resize the image.');
            }
            ob_start();
            try {
                $encoded = match ($format) {
                    'jpeg' => @imagejpeg($target, null, $this->quality ?? 85),
                    'png' => @imagepng($target, null, 9),
                    'webp' => @imagewebp($target, null, $this->quality ?? 85),
                };
                $output = ob_get_contents();
                if (!$encoded || $output === false) {
                    throw new RuntimeException('GD could not encode the image.');
                }

                return $output;
            } finally {
                ob_end_clean();
            }
        } finally {
            // GdImage resources are released when references leave scope (PHP 8+).
            unset($source, $target);
        }
    }
}
