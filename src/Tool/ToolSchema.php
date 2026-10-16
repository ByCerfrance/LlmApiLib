<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

/** @internal Restores JSON Schema objects lost by associative PHP decoding. */
readonly class ToolSchema
{
    /** @param array<string, mixed>|object $schema */
    public static function normalize(array|object $schema): object
    {
        $schema = (array) $schema;
        foreach ($schema as $keyword => $value) {
            if (!is_array($value) && !is_object($value)) {
                continue;
            }
            if (in_array($keyword, ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'], true)) {
                $schema[$keyword] = (object) array_map(
                    static fn(mixed $entry): mixed => is_array($entry) || is_object($entry) ? self::normalize($entry) : $entry,
                    (array) $value,
                );
            } elseif (in_array($keyword, ['allOf', 'anyOf', 'oneOf', 'prefixItems'], true)
                || ($keyword === 'items' && is_array($value) && $value !== [] && array_is_list($value))) {
                $schema[$keyword] = array_map(
                    static fn(mixed $entry): mixed => is_array($entry) || is_object($entry) ? self::normalize($entry) : $entry,
                    (array) $value,
                );
            } elseif (in_array($keyword, ['additionalProperties', 'unevaluatedProperties', 'unevaluatedItems', 'items', 'contains', 'not', 'if', 'then', 'else', 'propertyNames'], true)) {
                $schema[$keyword] = self::normalize($value);
            }
        }

        return (object) $schema;
    }
}
