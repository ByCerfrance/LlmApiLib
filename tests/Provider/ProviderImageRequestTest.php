<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageRequestInterface;
use ByCerfrance\LlmApiLib\Model\Model;
use ByCerfrance\LlmApiLib\Provider\ProviderImageRequest;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderImageRequest::class)]
#[UsesClass(ImageRequest::class)]
#[UsesClass(Model::class)]
class ProviderImageRequestTest extends TestCase
{
    public function testValidRequestIsKept(): void
    {
        $request = new ImageRequest('Draw', aspectRatio: '1:1');
        $this->assertSame($request, (new ProviderImageRequest(new Model('model'), $request))->request);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'empty prompt' => ['', '1:1'];
        yield 'malformed aspect ratio' => ['Draw', 'wide'];
    }

    #[DataProvider('invalidRequests')]
    public function testThirdPartyRequestsFollowTheSameRules(string $prompt, string $aspectRatio): void
    {
        $request = new readonly class($prompt, $aspectRatio) implements ImageRequestInterface {
            public function __construct(private string $prompt, private string $aspectRatio)
            {
            }

            #[Override]
            public function getPrompt(): string
            {
                return $this->prompt;
            }

            #[Override]
            public function getCount(): int
            {
                return 1;
            }

            #[Override]
            public function getOptions(): array
            {
                return [];
            }

            #[Override]
            public function getAspectRatio(): ?string
            {
                return $this->aspectRatio;
            }
        };
        $this->expectException(InvalidArgumentException::class);
        new ProviderImageRequest(new Model('model'), $request);
    }
}
