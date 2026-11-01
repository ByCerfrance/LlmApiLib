<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use RuntimeException;

interface TextNormalizerInterface
{
    /**
     * Normalize text according to its explicitly declared format.
     *
     * Unknown text permits whole-document JSON object/array detection only.
     * Whitespace cleanup requires PLAIN_TEXT; JSON strings and number representations
     * must be preserved. Invalid JSON remains unchanged in UNKNOWN and JSON formats.
     *
     * @param string $text Original text.
     * @param TextFormat $format Application-declared format, never inferred from a message role.
     * @return string Normalized text.
     * @throws RuntimeException When normalization cannot be performed.
     */
    public function normalize(string $text, TextFormat $format = TextFormat::UNKNOWN): string;
}
