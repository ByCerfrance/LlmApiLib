<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

interface ProviderInterface
{
    /** @return string Identifier of the configured provider connection. */
    public function getId(): string;
}
