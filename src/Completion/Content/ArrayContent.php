<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Override;
use Traversable;

readonly class ArrayContent implements ContentInterface, IteratorAggregate
{
    private array $contents;

    public function __construct(ContentInterface|iterable|string|null ...$content)
    {
        $this->contents = iterator_to_array($this->prepare($content), false);
    }

    private function prepare(iterable $contents): iterable
    {
        foreach ($contents as $content) {
            if (null === $content) {
                continue;
            }

            if (is_iterable($content)) {
                yield from $this->prepare($content);
                continue;
            }

            if (is_scalar($content)) {
                yield new TextContent((string)$content);
                continue;
            }

            if (!$content instanceof ContentInterface) {
                throw new InvalidArgumentException('ArrayContent accept only ContentInterface or string types');
            }

            yield $content;
        }
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

    #[Override]
    public function requiredCapabilities(): array
    {
        return array_unique(
            array_merge(
                ...array_map(
                    fn(ContentInterface $content) => $content->requiredCapabilities(),
                    $this->contents,
                )
            ),
            SORT_REGULAR,
        );
    }
}
