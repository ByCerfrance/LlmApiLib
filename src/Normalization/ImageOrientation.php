<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

/** @internal Reads the EXIF orientation without requiring ext-exif or decoding pixels. */
final readonly class ImageOrientation
{
    public static function read(string $bytes, string $format): int
    {
        $length = strlen($bytes);
        $offset = match ($format) { 'jpeg' => 2, 'png' => 8, default => 12 };
        while ($offset + 8 <= $length) {
            if ($format === 'jpeg') {
                if ($bytes[$offset] !== "\xff") {
                    break;
                }
                while ($offset < $length && $bytes[$offset] === "\xff") {
                    $offset++;
                }
                if ($offset + 3 > $length || $bytes[$offset] === "\xda" || $bytes[$offset] === "\xd9") {
                    break;
                }
                $marker = $bytes[$offset++];
                $size = unpack('n', substr($bytes, $offset, 2))[1];
                if ($size < 2 || $offset + $size > $length) {
                    break;
                }
                $data = substr($bytes, $offset + 2, $size - 2);
                if ($marker === "\xe1" && str_starts_with($data, "Exif\0\0")) {
                    return self::fromTiff(substr($data, 6));
                }
                $offset += $size;
            } else {
                $png = $format === 'png';
                $size = unpack($png ? 'N' : 'V', substr($bytes, $offset + ($png ? 0 : 4), 4))[1];
                $type = substr($bytes, $offset + ($png ? 4 : 0), 4);
                if ($offset + 8 + $size > $length) {
                    break;
                }
                if ($type === 'eXIf' || $type === 'EXIF') {
                    $data = substr($bytes, $offset + 8, $size);
                    return self::fromTiff(str_starts_with($data, "Exif\0\0") ? substr($data, 6) : $data);
                }
                $offset += $size + ($png ? 12 : 8 + ($size % 2));
            }
        }

        return 1;
    }

    private static function fromTiff(string $data): int
    {
        $length = strlen($data);
        if ($length < 8 || (!str_starts_with($data, "II\x2a\0") && !str_starts_with($data, "MM\0\x2a"))) {
            return 1;
        }
        $short = $data[0] === 'I' ? 'v' : 'n';
        $long = $data[0] === 'I' ? 'V' : 'N';
        $offset = unpack($long, substr($data, 4, 4))[1];
        if ($offset < 8 || $offset + 2 > $length) {
            return 1;
        }
        $count = unpack($short, substr($data, $offset, 2))[1];
        $offset += 2;
        for ($index = 0; $index < $count && $offset + 12 <= $length; $index++, $offset += 12) {
            $tag = unpack($short, substr($data, $offset, 2))[1];
            $type = unpack($short, substr($data, $offset + 2, 2))[1];
            $values = unpack($long, substr($data, $offset + 4, 4))[1];
            if ($tag === 0x0112 && $type === 3 && $values === 1) {
                $orientation = unpack($short, substr($data, $offset + 8, 2))[1];
                return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
            }
        }

        return 1;
    }
}
