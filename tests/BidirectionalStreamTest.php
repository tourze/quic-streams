<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\Streams\BidirectionalStream;

/**
 * BidirectionalStream 单元测试
 *
 * @internal
 */
#[CoversClass(BidirectionalStream::class)]
final class BidirectionalStreamTest extends TestCase
{
    private BidirectionalStream $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = new BidirectionalStream(0); // 客户端发起的双向流
    }

    public function testConstructorCreatesBidirectionalStream(): void
    {
        $this->assertSame(0, $this->stream->getId());
        $this->assertSame(StreamType::CLIENT_BIDI, $this->stream->getType());
    }

    public function testServerInitiatedStream(): void
    {
        $serverStream = new BidirectionalStream(1); // 服务端发起的双向流

        $this->assertSame(1, $serverStream->getId());
        $this->assertSame(StreamType::SERVER_BIDI, $serverStream->getType());
    }

    public function testSendData(): void
    {
        $testData = 'Hello World';

        $this->stream->send($testData);

        // 验证统计信息中包含发送缓冲区大小
        $stats = $this->stream->getStats();
        $this->assertGreaterThan(0, $stats['send_buffer_size']);
    }

    public function testReceiveData(): void
    {
        $testData = 'Hello World';

        $this->stream->receive($testData, 0);

        // 验证数据已被接收
        $receivedData = $this->stream->getReceivedData();
        $this->assertNotEmpty($receivedData);
        $this->assertSame($testData, $receivedData[0]['data']);
        $this->assertSame(strlen($testData), $receivedData[0]['length']);
    }

    public function testSendWithFin(): void
    {
        $testData = 'Final message';

        $this->stream->send($testData, true);

        // 验证统计信息显示发送状态
        $stats = $this->stream->getStats();
        $this->assertArrayHasKey('send_state', $stats);
    }

    public function testReceiveWithFin(): void
    {
        $testData = 'Final message';

        $this->stream->receive($testData, 0, true);

        // 验证统计信息显示接收状态
        $stats = $this->stream->getStats();
        $this->assertArrayHasKey('recv_state', $stats);
    }

    public function testResetStream(): void
    {
        $this->stream->send('Some data');

        $this->stream->reset();

        // 验证统计信息显示重置状态
        $stats = $this->stream->getStats();
        $this->assertSame('RESET_SENT', $stats['send_state']);
    }

    public function testIsCompleted(): void
    {
        $this->assertFalse($this->stream->isCompleted());

        // 发送数据并标记完成
        $this->stream->send('data', true);
        $this->stream->receive('response', 0, true);

        // 双向流需要双方都完成才算完成
        $this->assertTrue($this->stream->isCompleted());
    }

    public function testClearReceivedData(): void
    {
        // 先接收一些数据
        $this->stream->receive('test data 1', 0);
        $this->stream->receive('test data 2', 11);

        // 验证数据已被接收
        $receivedData = $this->stream->getReceivedData();
        $this->assertCount(2, $receivedData);

        // 清空接收缓冲区
        $this->stream->clearReceivedData();

        // 验证缓冲区已被清空
        $this->assertEmpty($this->stream->getReceivedData());
    }
}
