<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Tool;

use ByCerfrance\LlmApiLib\Completion\CompletionInterface;
use ByCerfrance\LlmApiLib\LlmInterface;
use Closure;
use Override;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Stringable;

/**
 * A tool that delegates its execution to a LLM provider.
 *
 * The promptBuilder callable transforms the tool arguments (sent by the orchestrator LLM)
 * into a CompletionInterface to be sent to the sub-model.
 *
 * Two calling modes are supported for the promptBuilder:
 * - Array mode: fn(array $args) => CompletionInterface
 * - Typed mode: fn(string $contenu, string $format = 'json') => CompletionInterface
 *   (arguments are filtered and passed as named parameters)
 */
readonly class LlmTool extends AbstractTool
{
    private Closure $promptBuilder;

    /**
     * @param string $name Tool name (must match pattern ^[a-zA-Z0-9_-]+$)
     * @param string $description Tool description for the LLM
     * @param array $parameters JSON Schema for parameters
     * @param LlmInterface $llm The LLM provider to delegate execution to
     * @param callable $promptBuilder Transforms tool arguments into a CompletionInterface
     */
    public function __construct(
        string $name,
        string $description,
        array $parameters,
        private LlmInterface $llm,
        callable $promptBuilder,
    ) {
        parent::__construct($name, $description, $parameters);
        $this->promptBuilder = $promptBuilder(...);
    }

    #[Override]
    public function execute(array $arguments): mixed
    {
        $completion = $this->buildCompletion($arguments);
        $response = $this->llm->chat($completion);

        $message = $response->getLastMessage()
            ?? throw new RuntimeException('LLM returned no message');
        $content = $message->getContent();

        if ($content instanceof Stringable || method_exists($content, '__toString')) {
            return (string)$content;
        }

        return json_encode($content->jsonSerialize(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get the underlying LLM provider.
     *
     * Useful for aggregating usage/cost across all LLM tools.
     *
     * @return LlmInterface
     */
    public function getLlm(): LlmInterface
    {
        return $this->llm;
    }

    /**
     * Build the completion by calling the promptBuilder with the appropriate calling convention.
     *
     * @param array $arguments
     *
     * @return CompletionInterface
     */
    private function buildCompletion(array $arguments): CompletionInterface
    {
        $ref = new ReflectionFunction($this->promptBuilder);
        $params = $ref->getParameters();

        // Array mode: single parameter typed as array
        if ($this->isArrayMode($params)) {
            return ($this->promptBuilder)($arguments);
        }

        // Typed mode: filter arguments to match parameter names, then unpack as named arguments
        $validNames = array_map(
            fn(ReflectionParameter $p) => $p->getName(),
            $params,
        );
        $filtered = array_intersect_key($arguments, array_flip($validNames));

        return ($this->promptBuilder)(...$filtered);
    }

    /**
     * Check if the promptBuilder expects a single array parameter.
     *
     * @param ReflectionParameter[] $params
     *
     * @return bool
     */
    private function isArrayMode(array $params): bool
    {
        if (count($params) !== 1) {
            return false;
        }

        $type = $params[0]->getType();

        return $type instanceof ReflectionNamedType && $type->getName() === 'array';
    }
}
