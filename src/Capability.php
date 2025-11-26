<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib;

enum Capability: string
{
    case AUDIO = 'audio';
    case CODE = 'code';
    case DOCUMENT = 'document';
    case IMAGE = 'image';
    case JSON_OUTPUT = 'json_output';
    case JSON_SCHEMA = 'json_schema';
    case MULTIMODAL = 'multimodal';
    case OCR = 'ocr';
    case REASONING = 'reasoning';
    case TEXT = 'text';
    case TOOLS = 'tools';
    case VIDEO = 'video';

    /**
     * Multiple from string.
     *
     * @param string $value
     * @param string $separator
     *
     * @return self[]
     */
    public static function multipleFromString(string $value, string $separator = ' '): array
    {
        $value = explode($separator, $value);
        $value = array_map('trim', $value);
        $value = array_unique(array_filter($value));

        return array_map(fn(string $v) => self::from($v), $value);
    }
}
