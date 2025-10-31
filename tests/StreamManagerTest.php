<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\Streams\BidirectionalStream;
use Tourze\QUIC\Streams\Exception\StreamException;
use Tourze\QUIC\Streams\StreamManager;
use Tourze\QUIC\Streams\UnidirectionalStream;

/**
 * StreamManager 单元测试
 *
 * @internal
 */
#[CoversClass(StreamManager::class)]
final class StreamManagerTest extends TestCase
{
    private StreamManager $clientManager;

    private StreamManager $serverManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientManager = new StreamManager(false); // 客户端管理器
        $this->serverManager = new StreamManager(true);  // 服务端管理器
    }

    public function testCreateClientBidirectionalStream(): void
    {
        $stream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);

        $this->assertInstanceOf(BidirectionalStream::class, $stream);
        $this->assertSame(StreamType::CLIENT_BIDI, $stream->getType());
        $this->assertSame(0, $stream->getId()); // 第一个客户端双向流ID应该是0
    }

    public function testCreateServerBidirectionalStream(): void
    {
        $stream = $this->serverManager->createStream(StreamType::SERVER_BIDI);

        $this->assertInstanceOf(BidirectionalStream::class, $stream);
        $this->assertSame(StreamType::SERVER_BIDI, $stream->getType());
        $this->assertSame(1, $stream->getId()); // 第一个服务端双向流ID应该是1
    }

    public function testCreateClientUnidirectionalStream(): void
    {
        $stream = $this->clientManager->createStream(StreamType::CLIENT_UNI);

        $this->assertInstanceOf(UnidirectionalStream::class, $stream);
        $this->assertSame(StreamType::CLIENT_UNI, $stream->getType());
        $this->assertSame(2, $stream->getId()); // 第一个客户端单向流ID应该是2
    }

    public function testCreateServerUnidirectionalStream(): void
    {
        $stream = $this->serverManager->createStream(StreamType::SERVER_UNI);

        $this->assertInstanceOf(UnidirectionalStream::class, $stream);
        $this->assertSame(StreamType::SERVER_UNI, $stream->getType());
        $this->assertSame(3, $stream->getId()); // 第一个服务端单向流ID应该是3
    }

    public function testGetExistingStream(): void
    {
        $stream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $streamId = $stream->getId();

        $retrievedStream = $this->clientManager->getStream($streamId);

        $this->assertSame($stream, $retrievedStream);
    }

    public function testGetNonExistingStream(): void
    {
        $stream = $this->clientManager->getStream(999);

        $this->assertNull($stream);
    }

    public function testGetOrCreateExistingStream(): void
    {
        $stream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $streamId = $stream->getId();

        $retrievedStream = $this->clientManager->getOrCreateStream($streamId);

        $this->assertSame($stream, $retrievedStream);
    }

    public function testGetOrCreateNewStream(): void
    {
        $stream = $this->clientManager->getOrCreateStream(4); // 客户端双向流

        $this->assertInstanceOf(BidirectionalStream::class, $stream);
        $this->assertSame(4, $stream->getId());
    }

    public function testRemoveStream(): void
    {
        $stream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $streamId = $stream->getId();

        $removed = $this->clientManager->removeStream($streamId);

        $this->assertTrue($removed);
        $this->assertNull($this->clientManager->getStream($streamId));
    }

    public function testRemoveNonExistingStream(): void
    {
        $removed = $this->clientManager->removeStream(999);

        $this->assertFalse($removed);
    }

    public function testGetAllStreams(): void
    {
        $stream1 = $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $stream2 = $this->clientManager->createStream(StreamType::CLIENT_UNI);

        $allStreams = $this->clientManager->getAllStreams();

        $this->assertCount(2, $allStreams);
        $this->assertContains($stream1, $allStreams);
        $this->assertContains($stream2, $allStreams);
    }

    public function testGetStreamsByType(): void
    {
        $bidiStream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $uniStream = $this->clientManager->createStream(StreamType::CLIENT_UNI);

        $bidiStreams = $this->clientManager->getStreamsByType(StreamType::CLIENT_BIDI);
        $uniStreams = $this->clientManager->getStreamsByType(StreamType::CLIENT_UNI);

        $this->assertCount(1, $bidiStreams);
        $this->assertCount(1, $uniStreams);
        $this->assertContains($bidiStream, $bidiStreams);
        $this->assertContains($uniStream, $uniStreams);
    }

    public function testGetStreamCount(): void
    {
        $this->assertSame(0, $this->clientManager->getStreamCount());

        $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $this->clientManager->createStream(StreamType::CLIENT_UNI);

        $this->assertSame(2, $this->clientManager->getStreamCount());
        $this->assertSame(1, $this->clientManager->getStreamCount(StreamType::CLIENT_BIDI));
        $this->assertSame(1, $this->clientManager->getStreamCount(StreamType::CLIENT_UNI));
    }

    public function testSetAndGetMaxStreams(): void
    {
        $this->clientManager->setMaxStreams(StreamType::CLIENT_BIDI, 5);

        $maxStreams = $this->clientManager->getMaxStreams(StreamType::CLIENT_BIDI);

        $this->assertSame(5, $maxStreams);
    }

    public function testStreamLimitExceeded(): void
    {
        $this->clientManager->setMaxStreams(StreamType::CLIENT_BIDI, 1);

        // 创建第一个流应该成功
        $this->clientManager->createStream(StreamType::CLIENT_BIDI);

        // 创建第二个流应该失败
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream limit exceeded');

        $this->clientManager->createStream(StreamType::CLIENT_BIDI);
    }

    public function testGetStats(): void
    {
        $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $this->clientManager->createStream(StreamType::CLIENT_UNI);

        $stats = $this->clientManager->getStats();

        $this->assertArrayHasKey('total_streams', $stats);
        $this->assertSame(2, $stats['total_streams']);
    }

    public function testReset(): void
    {
        $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $this->clientManager->createStream(StreamType::CLIENT_UNI);

        $this->clientManager->reset();

        $this->assertSame(0, $this->clientManager->getStreamCount());
        $this->assertEmpty($this->clientManager->getAllStreams());
    }

    public function testCreateStream(): void
    {
        $initialCount = $this->clientManager->getStreamCount();

        $stream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);

        $this->assertInstanceOf(BidirectionalStream::class, $stream);
        $this->assertSame($initialCount + 1, $this->clientManager->getStreamCount());
        $this->assertSame($stream, $this->clientManager->getStream($stream->getId()));
    }

    public function testGarbageCollect(): void
    {
        // 创建一个流
        $stream = $this->clientManager->createStream(StreamType::CLIENT_BIDI);
        $this->assertInstanceOf(BidirectionalStream::class, $stream);
        $this->assertSame(1, $this->clientManager->getStreamCount());

        // 当流未完成时，垃圾回收不应删除任何流
        $removedCount = $this->clientManager->garbageCollect();
        $this->assertSame(0, $removedCount);
        $this->assertSame(1, $this->clientManager->getStreamCount());

        // 模拟流完成（通过发送和接收数据并标记结束）
        $stream->send('test', true);
        $stream->receive('response', 0, true);

        // 确保流已完成（需要转换为具体类型）
        $this->assertTrue($stream->isCompleted());

        // 现在垃圾回收应该清理已完成的流
        $removedCount = $this->clientManager->garbageCollect();
        $this->assertSame(1, $removedCount);
        $this->assertSame(0, $this->clientManager->getStreamCount());
    }
}
