<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Core\Enum\StreamRecvState;
use Tourze\QUIC\Core\Enum\StreamSendState;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\FlowControl\StreamFlowController;
use Tourze\QUIC\Streams\Exception\StreamException;
use Tourze\QUIC\Streams\Stream;

/**
 * Stream 基类单元测试
 */
final class StreamTest extends TestCase
{
    private TestableStream $stream;

    protected function setUp(): void
    {
        $this->stream = new TestableStream(0);
    }

    public function test_constructor_initializes_stream_correctly(): void
    {
        $this->assertSame(0, $this->stream->getId());
        $this->assertSame(StreamType::CLIENT_BIDI, $this->stream->getType());
        $this->assertSame(StreamSendState::READY, $this->stream->getSendState());
        $this->assertSame(StreamRecvState::RECV, $this->stream->getRecvState());
    }

    public function test_different_stream_types(): void
    {
        $clientUni = new TestableStream(2);
        $this->assertSame(StreamType::CLIENT_UNI, $clientUni->getType());

        $serverUni = new TestableStream(3);
        $this->assertSame(StreamType::SERVER_UNI, $serverUni->getType());

        $serverBidi = new TestableStream(1);
        $this->assertSame(StreamType::SERVER_BIDI, $serverBidi->getType());
    }

    public function test_send_appends_data_to_buffer(): void
    {
        $data = 'Hello World';
        $this->stream->send($data);

        $sendBuffer = $this->stream->getSendBuffer();
        $this->assertSame($data, $sendBuffer);
        $this->assertSame(StreamSendState::SEND, $this->stream->getSendState());
    }

    public function test_send_with_fin_sets_fin_flag(): void
    {
        $data = '';
        $this->stream->send($data, true);

        $this->assertTrue($this->stream->isFinSent());
        $this->assertSame(StreamSendState::DATA_SENT, $this->stream->getSendState());
    }

    public function test_send_throws_exception_when_not_ready(): void
    {
        $this->stream->reset();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream not ready for sending');
        $this->expectExceptionCode(QuicError::STREAM_STATE_ERROR->value);

        $this->stream->send('data');
    }

    public function test_receive_stores_data_in_order(): void
    {
        $data1 = 'Hello';
        $data2 = ' World';

        $this->stream->receive($data1, 0);
        $this->stream->receive($data2, 5);

        $receivedData = $this->stream->getReceivedData();
        $this->assertSame('Hello World', $receivedData);
    }

    public function test_receive_handles_out_of_order_data(): void
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

    public function test_receive_with_fin_updates_state(): void
    {
        $data = 'Final message';
        $this->stream->receive($data, 0, true);

        $this->assertTrue($this->stream->isFinReceived());
        $this->assertSame(StreamRecvState::DATA_RECVD, $this->stream->getRecvState());
        $this->assertTrue($this->stream->isStreamCompleted());
    }

    public function test_receive_throws_exception_when_not_ready(): void
    {
        $this->stream->handleReset();

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream not ready for receiving');
        $this->expectExceptionCode(QuicError::STREAM_STATE_ERROR->value);

        $this->stream->receive('data', 0);
    }

    public function test_receive_with_flow_control(): void
    {
        $flowController = $this->createMock(StreamFlowController::class);
        $flowController->expects($this->once())
            ->method('canReceive')
            ->with(5)
            ->willReturn(false);

        $stream = new TestableStream(0, $flowController);

        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Stream data limit exceeded');
        $this->expectExceptionCode(QuicError::FLOW_CONTROL_ERROR->value);

        $stream->receive('Hello', 0);
    }

    public function test_reset_updates_state(): void
    {
        $this->stream->send('Some data');
        $this->stream->reset();

        $this->assertSame(StreamSendState::RESET_SENT, $this->stream->getSendState());
        $this->assertSame('', $this->stream->getSendBuffer());
    }

    public function test_handle_reset_updates_both_states(): void
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

/**
 * 可测试的 Stream 具体实现
 */
class TestableStream extends Stream
{
    private string $receivedData = '';
    private bool $streamCompleted = false;

    protected function onDataReceived(string $data): void
    {
        $this->receivedData .= $data;
    }

    protected function onStreamCompleted(): void
    {
        $this->streamCompleted = true;
    }

    public function getSendBuffer(): string
    {
        return $this->sendBuffer;
    }

    public function getReceivedData(): string
    {
        return $this->receivedData;
    }

    public function isFinSent(): bool
    {
        return $this->finSent;
    }

    public function isFinReceived(): bool
    {
        return $this->finReceived;
    }

    public function isStreamCompleted(): bool
    {
        return $this->streamCompleted;
    }
}