<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload;

interface BuilderInterface
{
    public function supports(mixed $value, BuildContext $context): bool;

    public function build(mixed $value, BuildContext $context): mixed;
}
