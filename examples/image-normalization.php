<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Examples;

use ByCerfrance\LlmApiLib\Content\ImageContent;
use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Normalization\GdImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\ImageNormalizer;
use ByCerfrance\LlmApiLib\Normalization\ImagickImageNormalizer;
use InvalidArgumentException;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = $argv[1] ?? dirname(__DIR__) . '/tests/Content/image.png';
$backend = $argv[2] ?? 'auto';
$localPath = realpath($path);
$bytes = $localPath === false || !is_file($localPath) ? false : @file_get_contents($localPath);
if ($bytes === false || ($info = @getimagesizefromstring($bytes)) === false) {
    throw new RuntimeException('Supply a readable JPEG, PNG or WebP file.');
}
$original = new ImageContent(ImageAsset::fromBytes($bytes, $info['mime']));
$normalizer = match ($backend) {
    'auto' => new ImageNormalizer(maxWidth: 1024, maxHeight: 1024, quality: 80, format: 'webp'),
    'gd' => new GdImageNormalizer(maxWidth: 1024, maxHeight: 1024, quality: 80, format: 'webp'),
    'imagick' => new ImagickImageNormalizer(maxWidth: 1024, maxHeight: 1024, quality: 80, format: 'webp'),
    default => throw new InvalidArgumentException('Backend must be auto, gd or imagick.'),
};
$normalized = $normalizer->normalize($original);
$output = $normalized->image->getBytes();
$size = getimagesizefromstring($output);
echo sprintf("Original: %d x %d, %s, %d bytes\n", $info[0], $info[1], $info['mime'], strlen($bytes));
echo sprintf("Normalized: %d x %d, %s, %d bytes\n", $size[0], $size[1], $size['mime'], strlen($output));
echo 'Reused result: ' . ($normalizer->normalize($normalized) === $normalized ? 'yes' : 'no') . PHP_EOL;
