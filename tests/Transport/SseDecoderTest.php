<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Transport;

use ByCerfrance\LlmApiLib\Transport\SseDecoder;
use ByCerfrance\LlmApiLib\Transport\SseEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SseDecoder::class)]
#[UsesClass(SseEvent::class)]
class SseDecoderTest extends TestCase
{
    public function testBytewiseUtf8CrLfMultilineAndMetadata(): void
    {
        $bytes = "\xEF\xBB\xBF: heartbeat\r\nid: 42\r\nevent: update\r\nretry: 20\r\ndata: hé\r\ndata: world\r\n\r\ndata: next\r\r";
        $events = iterator_to_array((new SseDecoder())->events(str_split($bytes)), false);
        $this->assertCount(2, $events);
        $this->assertSame("hé\nworld", $events[0]->data);
        $this->assertSame('update', $events[0]->event);
        $this->assertSame('42', $events[1]->id);
        $this->assertSame(20, $events[0]->retry);
    }

    public function testIncompleteEventIsNotDispatchedAtEof(): void
    {
        $this->assertSame([], iterator_to_array((new SseDecoder())->events(["data: incomplete\n"])));
    }

    public function testFrameLimitIsEnforcedAcrossChunks(): void
    {
        $decoder = new SseDecoder(10);
        $decoder->feed('data: ');
        $this->expectException(RuntimeException::class);
        $decoder->feed('too much data');
    }
}
