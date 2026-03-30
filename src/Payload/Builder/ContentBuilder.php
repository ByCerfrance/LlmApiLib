<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Payload\Builder;

use ByCerfrance\LlmApiLib\Completion\Content\ArrayContent;
use ByCerfrance\LlmApiLib\Completion\Content\ContentInterface;
use ByCerfrance\LlmApiLib\Completion\Content\DocumentUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\ImageUrlContent;
use ByCerfrance\LlmApiLib\Completion\Content\InputAudioContent;
use ByCerfrance\LlmApiLib\Completion\Content\JsonContent;
use ByCerfrance\LlmApiLib\Completion\Content\TextContent;
use ByCerfrance\LlmApiLib\Payload\BuildContext;
use ByCerfrance\LlmApiLib\Payload\BuilderInterface;
use ByCerfrance\LlmApiLib\Payload\PayloadBuilder;

readonly class ContentBuilder implements BuilderInterface
{
    public function supports(mixed $value, BuildContext $context): bool
    {
        return $value instanceof ContentInterface;
    }

    /**
     * @param ContentInterface $value
     */
    public function build(mixed $value, PayloadBuilder $payloadBuilder, BuildContext $context): mixed
    {
        return $this->buildContent($value, $payloadBuilder, $context, false);
    }

    private function buildContent(
        ContentInterface $content,
        PayloadBuilder $payloadBuilder,
        BuildContext $context,
        bool $encapsulated,
    ): mixed {
        if ($content instanceof ArrayContent) {
            return array_map(
                fn(ContentInterface $item) => $this->buildContent($item, $payloadBuilder, $context, true),
                iterator_to_array($content->getIterator(), false),
            );
        }

        if ($content instanceof TextContent || $content instanceof JsonContent) {
            if (!$encapsulated) {
                return $content->getContent();
            }

            return [
                'type' => 'text',
                'text' => $content->getContent(),
            ];
        }

        if ($content instanceof InputAudioContent) {
            return [
                'type' => 'input_audio',
                'input_audio' => [
                    'data' => $content->getData(),
                    'format' => $content->getFormat(),
                ],
            ];
        }

        if ($content instanceof ImageUrlContent) {
            return [
                'type' => 'image_url',
                'image_url' => array_filter([
                    'url' => (string)$content->getUrl(),
                    'detail' => $content->getDetail(),
                ]),
            ];
        }

        if ($content instanceof DocumentUrlContent) {
            return array_filter([
                'type' => 'document_url',
                'document_url' => array_filter([
                    'url' => (string)$content->getUrl(),
                    'detail' => $content->getDetail(),
                ]),
                'document_name' => $content->getName(),
            ]);
        }

        return $content->jsonSerialize($encapsulated);
    }
}
