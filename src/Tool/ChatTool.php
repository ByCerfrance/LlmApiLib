<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tool;

use ByCerfrance\LlmApiLib\Chat\ChatInterface;
use Closure;
use Override;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use Stringable;

/** Delegates a tool invocation to a configured chat executor. */
readonly class ChatTool extends AbstractTool
{
    private Closure $promptBuilder;

    /**
     * @param array<string, mixed> $parameters JSON Schema.
     * @param callable $promptBuilder Returns a ChatRequestInterface or string, using array or named-argument mode.
     */
    public function __construct(
        string $name,
        string $description,
        array $parameters,
        private ChatInterface $chat,
        callable $promptBuilder,
    ) {
        parent::__construct($name, $description, $parameters);
        $this->promptBuilder = $promptBuilder(...);
    }

    public function getChat(): ChatInterface
    {
        return $this->chat;
    }

    #[Override]
    public function execute(array $arguments): mixed
    {
        $parameters = (new ReflectionFunction($this->promptBuilder))->getParameters();
        $type = count($parameters) === 1 ? $parameters[0]->getType() : null;
        $request = $type instanceof ReflectionNamedType && $type->getName() === 'array'
            ? ($this->promptBuilder)($arguments)
            : ($this->promptBuilder)(...array_intersect_key($arguments, array_flip(array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            ))));
        $content = $this->chat->chat($request)->getResult()->getMessage()->getContent();

        return $content instanceof Stringable
            ? (string) $content
            : json_encode($content->jsonSerialize(), JSON_THROW_ON_ERROR);
    }
}
