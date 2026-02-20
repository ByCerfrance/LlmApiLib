<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use RuntimeException;

trait FileContentTrait
{
    private static function fileToBase64(string $path, ?string &$mimeType = null): string
    {
        // Open the temp file for reading
        if (false === ($in = fopen($path, 'rb'))) {
            throw new RuntimeException(sprintf('Unable to open temp file "%s" for reading', $path));
        }

        try {
            return self::streamToBase64($in, $mimeType);
        } finally {
            fclose($in);
        }
    }

    private static function streamToBase64(mixed $in, ?string &$mimeType = null): string
    {
        if (false === extension_loaded('fileinfo')) {
            throw new RuntimeException('Fileinfo extension is required to create a content from a file/stream');
        }

        try {
            $mimeType = mime_content_type($in);

            // Attach Base64 encoder as a READ filter (transform happens while reading)
            $filter = stream_filter_append(
                $in,
                'convert.base64-encode',
                STREAM_FILTER_READ,
                [
                    'line-length' => 0,
                    'line-breaks' => false,
                ]
            ) ?: throw new RuntimeException('Unable to attach Base64 READ filter');

            // Read back → you get Base64 text (not the original binary)
            return stream_get_contents($in, offset: 0) ?: throw new RuntimeException('Failed to read Base64 data');
        } finally {
            !empty($filter) && stream_filter_remove($filter);
        }
    }
}
