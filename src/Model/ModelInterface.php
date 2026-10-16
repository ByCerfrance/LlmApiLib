<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

interface ModelInterface
{
    /** @return string Provider model identifier. */
    public function getName(): string;
}
