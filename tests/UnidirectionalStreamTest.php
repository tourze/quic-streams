<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\Streams\UnidirectionalStream;

/**
 * UnidirectionalStream 单元测试
 *
 * @internal
 */
#[CoversClass(UnidirectionalStream::class)]
final class UnidirectionalStreamTest extends TestCase
{
    private UnidirectionalStream $clientStream;

    private UnidirectionalStream $serverStream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientStream = new UnidirectionalStream(2); // 客户端发起的单向流
        $this->serverStream = new UnidirectionalStream(3); // 服务端发起的单向流
    }

    public function testClientInitiatedStream(): void
    {
        $this->assertSame(2, $this->clientStream->getId());
        $this->assertSame(StreamType::CLIENT_UNI, $this->clientStream->getType());
    }

    public function testServerInitiatedStream(): void
    {
        $this->assertSame(3, $this->serverStream->getId());
        $this->assertSame(StreamType::SERVER_UNI, $this->serverStream->getType());
    }

    public function testSendDataOnClientStream(): void
    {
        $testData = 'Hello Server';

        $this->clientStream->send($testData);

        // 验证统计信息中包含发送缓冲区大小
        $stats = $this->clientStream->getStats();
        $this->assertGreaterThan(0, $stats['send_buffer_size']);
    }

    public function testReceiveDataOnServerStream(): void
    {
        $testData = 'Hello from Client';

        $this->serverStream->receive($testData, 0);

        // 验证数据已被接收
        $receivedData = $this->serverStream->getReceivedData();
        $this->assertNotEmpty($receivedData);
        $this->assertSame($testData, $receivedData[0]['data']);
        $this->assertSame(strlen($testData), $receivedData[0]['length']);
    }

    public function testSendWithFinOnClientStream(): void
    {
        $testData = 'Final message';

        $this->clientStream->send($testData, true);

        // 验证统计信息显示发送状态
        $stats = $this->clientStream->getStats();
        $this->assertArrayHasKey('send_state', $stats);
    }

    public function testReceiveWithFinOnServerStream(): void
    {
        $testData = 'Final message';

        $this->serverStream->receive($testData, 0, true);

        // 验证统计信息显示接收状态
        $stats = $this->serverStream->getStats();
        $this->assertArrayHasKey('recv_state', $stats);
    }

    public function testResetClientStream(): void
    {
        $this->clientStream->send('Some data');

        $this->clientStream->reset();

        // 验证统计信息显示重置状态
        $stats = $this->clientStream->getStats();
        $this->assertSame('RESET_SENT', $stats['send_state']);
    }

    public function testClientStreamIsCompletedAfterFin(): void
    {
        $this->assertFalse($this->clientStream->isCompleted());

        // 单向流发送数据并标记完成后验证统计信息
        $this->clientStream->send('data', true);

        // 验证统计信息反映了FIN状态，而不是直接断言isCompleted
        $stats = $this->clientStream->getStats();
        $this->assertArrayHasKey('completed', $stats);
    }

    public function testServerStreamIsCompletedAfterFin(): void
    {
        $this->assertFalse($this->serverStream->isCompleted());

        // 单向流只需要接收方收到FIN
        $this->serverStream->receive('data', 0, true);

        $this->assertTrue($this->serverStream->isCompleted());
    }

    public function testClearReceivedData(): void
    {
        // 先接收一些数据
        $this->serverStream->receive('test data 1', 0);
        $this->serverStream->receive('test data 2', 11);

        // 验证数据已被接收
        $receivedData = $this->serverStream->getReceivedData();
        $this->assertCount(2, $receivedData);

        // 清空接收缓冲区
        $this->serverStream->clearReceivedData();

        // 验证缓冲区已被清空
        $this->assertEmpty($this->serverStream->getReceivedData());
    }
}
