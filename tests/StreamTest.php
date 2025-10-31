<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Core\Enum\StreamRecvState;
use Tourze\QUIC\Core\Enum\StreamSendState;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\FlowControl\StreamFlowControl;
use Tourze\QUIC\Streams\Exception\StreamException;
use Tourze\QUIC\Streams\Stream;

/**
 * Stream 基类单元测试
 *
 * @internal
 */
#[CoversClass(Stream::class)]
final class StreamTest extends TestCase
{
    private TestableStream $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = new TestableStream(0);
    }

    public function testConstructorInitializesStreamCorrectly(): void
    {
        $this->assertSame(0, $this->stream->getId());
        $this->assertSame(StreamType::CLIENT_BIDI, $this->stream->getType());
        $this->assertSame(StreamSendState::READY, $this->stream->getSendState());
        $this->assertSame(StreamRecvState::RECV, $this->stream->getRecvState());
    }

    public function testDifferentStreamTypes(): void
    {
        $clientUni = new TestableStream(2);
        $this->assertSame(StreamType::CLIENT_UNI, $clientUni->getType());

        $serverUni = new TestableStream(3);
        $this->assertSame(StreamType::SERVER_UNI, $serverUni->getType());

        $serverBidi = new TestableStream(1);
        $this->assertSame(StreamType::SERVER_BIDI, $serverBidi->getType());
    }

    public function testSendAppendsDataToBuffer(): void
    {
        $data = 'Hello World';
        $this->stream->send($data);

        $sendBuffer = $this->stream->getSendBuffer();
        $this->assertSame($data, $sendBuffer);
        $this->assertSame(StreamSendState::SEND, $this->stream->getSendState());
    }

    public function testSendWithFinSetsFinFlag(): void
    {
        $data = '';
        $this->stream->send($data, true);

        $this->assertTrue($this->stream->isFinSent());
        $this->assertSame(StreamSendState::DATA_SENT, $this->stream->getSendState());
    }

    public function testSendThrowsExceptionWhenNotReady(): void
    {
        $this->stream->reset();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream not ready for sending');
        $this->expectExceptionCode(QuicError::STREAM_STATE_ERROR->value);

        $this->stream->send('data');
    }

    public function testReceiveStoresDataInOrder(): void
    {
        $data1 = 'Hello';
        $data2 = ' World';

        $this->stream->receive($data1, 0);
        $this->stream->receive($data2, 5);

        $receivedData = $this->stream->getReceivedData();
        $this->assertSame('Hello World', $receivedData);
    }

    public function testReceiveHandlesOutOfOrderData(): void
    {
        $data1 = 'World';
        $data2 = 'Hello ';

        // 先接收偏移量为 6 的数据
        $this->stream->receive($data1, 6);
        $receivedData = $this->stream->getReceivedData();
        $this->assertSame('', $receivedData); // 还没有按序数据

        // 再接收偏移量为 0 的数据
        $this->stream->receive($data2, 0);
        $receivedData = $this->stream->getReceivedData();
        $this->assertSame('Hello World', $receivedData);
    }

    public function testReceiveWithFinUpdatesState(): void
    {
        $data = 'Final message';
        $this->stream->receive($data, 0, true);

        $this->assertTrue($this->stream->isFinReceived());
        $this->assertSame(StreamRecvState::DATA_RECVD, $this->stream->getRecvState());
        $this->assertTrue($this->stream->isStreamCompleted());
    }

    public function testReceiveThrowsExceptionWhenNotReady(): void
    {
        $this->stream->handleReset();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream not ready for receiving');
        $this->expectExceptionCode(QuicError::STREAM_STATE_ERROR->value);

        $this->stream->receive('data', 0);
    }

    public function testReceiveWithFlowControl(): void
    {
        // 使用匿名类替代 Mock 以符合静态分析要求
        // 创建一个自定义的流控制器，拒绝接收5字节的数据
        $flowController = new class(0) extends StreamFlowControl {
            public function canReceive(int $bytes): bool
            {
                // 对于5字节数据返回false，模拟流控制限制
                return 5 !== $bytes;
            }
        };

        $stream = new TestableStream(0, $flowController);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream data limit exceeded');
        $this->expectExceptionCode(QuicError::FLOW_CONTROL_ERROR->value);

        $stream->receive('Hello', 0);
    }

    public function testResetUpdatesState(): void
    {
        $this->stream->send('Some data');
        $this->stream->reset();

        $this->assertSame(StreamSendState::RESET_SENT, $this->stream->getSendState());
        $this->assertSame('', $this->stream->getSendBuffer());
    }

    public function testHandleResetUpdatesBothStates(): void
    {
        $this->stream->send('Some data');
        $this->stream->receive('Response', 0);
        $this->stream->handleReset();

        $this->assertSame(StreamSendState::RESET_RECVD, $this->stream->getSendState());
        $this->assertSame(StreamRecvState::RESET_RECVD, $this->stream->getRecvState());
        $this->assertSame('', $this->stream->getSendBuffer());
        // 注意：handleReset 不会清除已处理的数据，只清除缓冲区
        $this->assertSame('Response', $this->stream->getReceivedData());
    }
}
