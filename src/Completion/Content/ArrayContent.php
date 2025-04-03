<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ArrayIterator;
use IteratorAggregate;
use Override;
use Traversable;

readonly class ArrayContent implements ContentInterface, IteratorAggregate
{
    private array $contents;

    public function __construct(ContentInterface ...$content)
    {
        $this->contents = $content;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->contents);
    }

    #[Override]
    public function jsonSerialize(bool $encapsulated = false): array
    {
        return array_map(
            fn(ContentInterface $content) => $content->jsonSerialize(true),
            $this->contents,
        );
    }
}
