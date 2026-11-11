<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAi;

use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Model\Capability;
use ByCerfrance\LlmApiLib\Provider\FailureKind;
use ByCerfrance\LlmApiLib\Provider\ImageProviderInterface;
use ByCerfrance\LlmApiLib\Provider\ImageResult;
use ByCerfrance\LlmApiLib\Provider\JsonTransport;
use ByCerfrance\LlmApiLib\Provider\ProviderException;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use ByCerfrance\LlmApiLib\Usage\TokenUsage;
use InvalidArgumentException;
use Override;
use Psr\Http\Client\ClientInterface;
use SensitiveParameter;
use Throwable;

readonly class OpenAiImages implements ImageProviderInterface
{
    private JsonTransport $transport;

    public function __construct(#[SensitiveParameter] private string $apiKey, ClientInterface $client, private string $id = 'OpenAiImages')
    {
        $this->transport = new JsonTransport($client);
    }

    #[Override]
    public function getId(): string
    {
        return $this->id;
    }

    #[Override]
    public function generate(ProviderImageRequest $request): ImageResult
    {
        $input = $request->request;
        $editing = $input->getImages() !== [];
        if ($editing && $request->model->supports(Capability::VISION) === false) {
            throw new InvalidArgumentException('Image editing requires a model supporting image input.');
        }
        if (count($input->getImages()) > 16) {
            throw new InvalidArgumentException('OpenAI image editing supports at most 16 input images.');
        }
        if ($request->model->supports(Capability::IMAGE_OUTPUT) === false || $input->getCount() > 10) {
            throw new InvalidArgumentException('Unsupported image model capability or count.');
        }
        $options = $input->getOptions();
        foreach ($options as $key => $value) {
            if (!in_array($key, ['size', 'quality', 'background', 'output_format', 'output_compression', 'moderation'], true)) {
                throw new InvalidArgumentException(sprintf('Unsupported image option "%s".', $key));
            }
            if ($key !== 'output_compression' && (!is_string($value) || $value === '')) {
                throw new InvalidArgumentException(sprintf('Image option "%s" requires a non-empty string.', $key));
            }
        }
        foreach (['background' => ['auto', 'transparent', 'opaque'], 'moderation' => ['auto', 'low']] as $key => $values) {
            if (isset($options[$key]) && !in_array($options[$key], $values, true)) {
                throw new InvalidArgumentException(sprintf('Invalid image option "%s".', $key));
            }
        }
        if (($options['output_format'] ?? null) !== null && !in_array($options['output_format'], ['png', 'jpeg', 'webp'], true)) {
            throw new InvalidArgumentException('Invalid image output format.');
        }
        if (isset($options['output_compression']) && (!is_int($options['output_compression']) || $options['output_compression'] < 0 || $options['output_compression'] > 100
            || !in_array($options['output_format'] ?? 'png', ['jpeg', 'webp'], true))) {
            throw new InvalidArgumentException('Compression requires jpeg/webp and an integer from 0 to 100.');
        }
        if ($input->getAspectRatio() !== null) {
            if (isset($options['size'])) {
                throw new InvalidArgumentException('Configure either an aspect ratio or an image size.');
            }
            $options['size'] = $this->size($input->getAspectRatio());
        }
        $body = ['model' => $request->model->getName(), 'prompt' => $input->getPrompt(), 'n' => $input->getCount(), ...$options];
        if ($editing) {
            $body['images'] = [];
        }
        foreach ($request->model->getUnsupportedParameters() as $field) {
            if (array_key_exists($field, $body)) {
                throw new InvalidArgumentException(sprintf('Image parameter "%s" is unsupported by this model.', $field));
            }
        }
        foreach ($request->model->getParameterValues() as $field => $values) {
            if (isset($body[$field]) && !in_array($body[$field], $values, true)) {
                throw new InvalidArgumentException(sprintf('Image parameter "%s" has an unsupported value.', $field));
            }
        }
        foreach ($input->getImages() as $image) {
            if (!in_array($image->mimeType, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw new InvalidArgumentException('OpenAI image inputs must use PNG, JPEG or WebP.');
            }
            $url = sprintf('data:%s;base64,%s', $image->mimeType, $image->getBase64());
            if (strlen($url) > 20971520) {
                throw new InvalidArgumentException('OpenAI image input data URLs must not exceed 20971520 characters.');
            }
            $body['images'][] = ['image_url' => $url];
        }
        $endpoint = $editing ? 'edits' : 'generations';
        $response = $this->transport->post($this->id, 'https://api.openai.com/v1/images/' . $endpoint, $body, ['Authorization' => 'Bearer ' . $this->apiKey]);
        $data = $this->transport->decode($response, $this->id);
        try {
            $images = [];
            if (!is_array($data['data'] ?? null) || !array_is_list($data['data']) || $data['data'] === []) {
                throw new InvalidArgumentException('Missing generated images.');
            }
            foreach ($data['data'] as $image) {
                $format = $data['output_format'] ?? $options['output_format'] ?? 'png';
                if (!in_array($format, ['png', 'jpeg', 'webp'], true)) {
                    throw new InvalidArgumentException('Unknown image response format.');
                }
                $images[] = new ImageAsset('image/' . $format, $image['b64_json'] ?? throw new InvalidArgumentException('Missing image data.'));
            }
            $usage = $data['usage'] ?? [];
            if (!is_array($usage)) {
                throw new InvalidArgumentException('Invalid image usage object.');
            }
            foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $key) {
                if (isset($usage[$key]) && (!is_int($usage[$key]) || $usage[$key] < 0)) {
                    throw new InvalidArgumentException('Invalid image usage.');
                }
            }
            return new ImageResult($images, new TokenUsage($usage['input_tokens'] ?? null, $usage['output_tokens'] ?? null, $usage['total_tokens'] ?? null),
                $this->id, $request->model->getName(), $response->getHeaderLine('x-request-id') ?: null);
        } catch (Throwable $failure) {
            throw new ProviderException('Invalid image response.', previous: $failure, kind: FailureKind::INVALID_RESPONSE, providerId: $this->id);
        }
    }

    /** Translate a portable aspect ratio into a GPT Image size. */
    private function size(string $aspectRatio): string
    {
        $sizes = ['1:1' => '1024x1024', '3:2' => '1536x1024', '2:3' => '1024x1536'];

        return $sizes[$aspectRatio] ?? throw new InvalidArgumentException(sprintf(
            'Aspect ratio "%s" is not supported; use %s.', $aspectRatio, implode(', ', array_keys($sizes)),
        ));
    }
}
