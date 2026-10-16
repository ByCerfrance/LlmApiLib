<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

use ByCerfrance\LlmApiLib\Usage\Pricing\Pricing;

interface ModelInterface
{
    /** @return string Provider model identifier. */
    public function getName(): string;

    /** @return Pricing|null Optional application-supplied token rates, separate from measurements. */
    public function getPricing(): ?Pricing;

    /** @return list<Capability>|null Complete known capabilities, or null when unknown. */
    public function getCapabilities(): ?array;

    /**
     * @param Capability $capability
     * @return bool|null True/false when known, null when unknown.
     */
    public function supports(Capability $capability): ?bool;

    /** @return int|null Known context window; no token counting is implied. */
    public function getMaxContextTokens(): ?int;

    /** @return int|null Known maximum output size. */
    public function getMaxOutputTokens(): ?int;

    /** @return bool|null Whether sampling is supported, or null when unknown. */
    public function isTunable(): ?bool;

    /** @return list<string> Unsupported generation-field paths, never content/schema paths. */
    public function getUnsupportedParameters(): array;

    /** @return array<string, list<string|int|float|bool>> Allowed values for known generation fields. */
    public function getParameterValues(): array;
}
