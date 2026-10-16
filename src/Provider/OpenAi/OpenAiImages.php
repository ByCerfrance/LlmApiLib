<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider\OpenAi;

use ByCerfrance\LlmApiLib\Image\ImageAsset;
use ByCerfrance\LlmApiLib\Image\ImageRequest;
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
        new ImageRequest($input->getPrompt(), $input->getCount(), $input->getOptions());
        if ($request->model->supports(Capability::IMAGE_OUTPUT) === false || $input->getCount() > 10) {
            throw new InvalidArgumentException('Unsupported image model capability or count.');
        }
        $options = $input->getOptions();
        if ($request->model->getName() === 'dall-e-3' && $input->getCount() !== 1) {
            throw new InvalidArgumentException('DALL-E 3 supports one image per request.');
        }
        foreach ($options as $key => $value) {
            if (!in_array($key, ['size', 'quality', 'background', 'output_format', 'output_compression', 'moderation', 'response_format'], true)) {
                throw new InvalidArgumentException(sprintf('Unsupported image option "%s".', $key));
            }
            if ($key !== 'output_compression' && (!is_string($value) || $value === '')) {
                throw new InvalidArgumentException(sprintf('Image option "%s" requires a non-empty string.', $key));
            }
        }
        foreach (['response_format' => ['url', 'b64_json'], 'background' => ['auto', 'transparent', 'opaque'], 'moderation' => ['auto', 'low']] as $key => $values) {
            if (isset($options[$key]) && !in_array($options[$key], $values, true)) {
                throw new InvalidArgumentException(sprintf('Invalid image option "%s".', $key));
            }
        }
        $gpt = str_starts_with($request->model->getName(), 'gpt-image-');
        if ($gpt && isset($options['response_format'])) {
            throw new InvalidArgumentException('GPT Image models return base64; response_format is unsupported.');
        }
        if (($options['output_format'] ?? null) !== null && !in_array($options['output_format'], ['png', 'jpeg', 'webp'], true)) {
            throw new InvalidArgumentException('Invalid image output format.');
        }
        if (isset($options['output_compression']) && (!is_int($options['output_compression']) || $options['output_compression'] < 0 || $options['output_compression'] > 100
            || !in_array($options['output_format'] ?? 'png', ['jpeg', 'webp'], true))) {
            throw new InvalidArgumentException('Compression requires jpeg/webp and an integer from 0 to 100.');
        }
        $body = ['model' => $request->model->getName(), 'prompt' => $input->getPrompt(), 'n' => $input->getCount(), ...$options];
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
        $response = $this->transport->post($this->id, 'https://api.openai.com/v1/images/generations', $body, ['Authorization' => 'Bearer ' . $this->apiKey]);
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
                $images[] = new ImageAsset(isset($image['b64_json']) ? 'image/' . $format : null, $image['b64_json'] ?? null, $image['url'] ?? null);
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
}
