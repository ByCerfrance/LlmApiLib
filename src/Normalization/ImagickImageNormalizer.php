<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use Imagick;
use ImagickException;
use Override;
use RuntimeException;

class ImagickImageNormalizer extends AbstractImageNormalizer
{
    #[Override]
    protected function getExtensionName(): string
    {
        return 'imagick';
    }

    #[Override]
    protected function encode(string $bytes, int $width, int $height, string $format, int $orientation): string
    {
        if (Imagick::queryFormats(strtoupper($format)) === []) {
            throw new RuntimeException(sprintf('The Imagick build does not support %s output.', $format));
        }
        $image = new Imagick();
        try {
            $image->readImageBlob($bytes);
            if ($image->getNumberImages() !== 1) {
                throw new RuntimeException('Only single-frame images can be normalized.');
            }
            if ($orientation >= 3 && $orientation !== 4) {
                $angle = match ($orientation) { 3 => 180, 8 => -90, default => 90 };
                $image->rotateImage('transparent', $angle);
            }
            if (in_array($orientation, [2, 5], true)) {
                $image->flopImage();
            } elseif (in_array($orientation, [4, 7], true)) {
                $image->flipImage();
            }
            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            if ($format === 'jpeg') {
                $image->setImageBackgroundColor('white');
                $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            }
            $image->setImageFormat($format);
            if ($format !== 'png') {
                $image->setImageCompressionQuality($this->quality ?? 85);
            }
            $image->stripImage();

            return $image->getImageBlob();
        } catch (ImagickException $exception) {
            throw new RuntimeException('Imagick could not normalize the image.', previous: $exception);
        } finally {
            $image->clear();
        }
    }
}
