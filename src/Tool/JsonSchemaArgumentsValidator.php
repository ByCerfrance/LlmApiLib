<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

use InvalidArgumentException;
use Opis\JsonSchema\Validator;
use Override;
use RuntimeException;
use Throwable;

readonly class JsonSchemaArgumentsValidator implements ArgumentsValidatorInterface
{
    public function __construct(private Validator $validator = new Validator())
    {
    }

    #[Override]
    public function validate(ToolInterface $tool, ToolCall $call): void
    {
        try {
            $schema = json_decode(json_encode(ToolSchema::normalize($tool->getParameters()), JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR);
            $data = json_decode($call->getArgumentsJson(), flags: JSON_THROW_ON_ERROR);
            $result = $this->validator->validate($data, $schema);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Cannot validate the schema for tool "%s".', $tool->getName()), previous: $exception);
        }
        if (!$result->isValid()) {
            throw new InvalidArgumentException(sprintf('Arguments do not match the JSON Schema for tool "%s".', $tool->getName()));
        }
    }

}
