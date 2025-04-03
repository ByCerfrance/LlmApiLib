<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Completion\Content;

readonly class InputAudio implements ContentInterface
{
    public function __construct(
        private string $data,
        private string $format,
    ) {
    }

    /**
     * Get data.
     *
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Get format.
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @param bool $encapsulated *
     *
     * @inheritDoc
     */
    public function jsonSerialize(bool $encapsulated = false): array
    {
        return array_filter([
            'type' => 'input_audio',
            'input_audio' => [
                'data' => $this->data,
                'format' => $this->format,
            ],
        ]);
    }
}
