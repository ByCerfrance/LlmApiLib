<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Normalization;

use InvalidArgumentException;
use Override;

readonly class TextNormalizer implements TextNormalizerInterface
{
    /**
     * All transformations are opt-in. Valid JSON objects/arrays bypass plain-text cleanup.
     *
     * @param bool $minifyJson Remove only JSON whitespace outside strings, without decoding values.
     * @param bool $trimText Trim ASCII spaces, tabs and line endings at document boundaries.
     * @param bool $trimLineEnds Remove ASCII spaces and tabs at the end of each line.
     * @param bool $collapseSpaces Replace each run of ASCII spaces/tabs with one space.
     * @param int|null $maxConsecutiveBlankLines Limit empty or space/tab-only lines; null leaves them unchanged.
     * @param bool $normalizeLineEndings Convert CRLF and CR to LF.
     * @throws InvalidArgumentException When the blank-line limit is negative.
     */
    public function __construct(
        private bool $minifyJson = false,
        private bool $trimText = false,
        private bool $trimLineEnds = false,
        private bool $collapseSpaces = false,
        private ?int $maxConsecutiveBlankLines = null,
        private bool $normalizeLineEndings = false,
    ) {
        if ($maxConsecutiveBlankLines !== null && $maxConsecutiveBlankLines < 0) {
            throw new InvalidArgumentException('Maximum consecutive blank lines must be non-negative.');
        }
    }

    #[Override]
    public function normalize(string $text, TextFormat $format = TextFormat::UNKNOWN): string
    {
        if ($this->isJsonDocument($text)) {
            return $this->minifyJson ? $this->minifyJsonDocument($text) : $text;
        }
        if ($format !== TextFormat::PLAIN_TEXT) {
            return $text;
        }

        if ($this->normalizeLineEndings) {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
        }
        if ($this->collapseSpaces) {
            $text = preg_replace('/[ \t]+/', ' ', $text);
        }
        if ($this->trimLineEnds || $this->maxConsecutiveBlankLines !== null) {
            $text = $this->normalizeLines($text);
        }

        $text = $this->trimText ? trim($text, " \t\r\n") : $text;

        // Plain-text cleanup can turn an invalid JSON-like string into a valid document.
        return $this->minifyJson && $this->isJsonDocument($text) ? $this->minifyJsonDocument($text) : $text;
    }

    private function isJsonDocument(string $text): bool
    {
        $candidate = ltrim($text, " \t\r\n");

        return $candidate !== '' && ($candidate[0] === '{' || $candidate[0] === '[') && json_validate($text);
    }

    private function minifyJsonDocument(string $text): string
    {
        $result = '';
        $inString = false;
        $escaped = false;
        $length = strlen($text);
        for ($index = 0; $index < $length; $index++) {
            $character = $text[$index];
            if ($inString) {
                $result .= $character;
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }
            } elseif ($character === '"') {
                $inString = true;
                $result .= $character;
            } elseif (!str_contains(" \t\r\n", $character)) {
                $result .= $character;
            }
        }

        return $result;
    }

    private function normalizeLines(string $text): string
    {
        $parts = preg_split('/(\r\n|\r|\n)/', $text, flags: PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $blankLines = 0;
        $count = count($parts);
        for ($index = 0; $index < $count; $index += 2) {
            $line = $this->trimLineEnds ? rtrim($parts[$index], " \t") : $parts[$index];
            $ending = $parts[$index + 1] ?? '';
            if ($ending !== '' && trim($line, " \t") === '') {
                $blankLines++;
                if ($this->maxConsecutiveBlankLines !== null && $blankLines > $this->maxConsecutiveBlankLines) {
                    continue;
                }
            } else {
                $blankLines = 0;
            }
            $result .= $line . $ending;
        }

        return $result;
    }
}
