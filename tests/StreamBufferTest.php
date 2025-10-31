<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Streams\StreamBuffer;

/**
 * StreamBuffer 单元测试
 *
 * @internal
 */
#[CoversClass(StreamBuffer::class)]
final class StreamBufferTest extends TestCase
{
    private StreamBuffer $buffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buffer = new StreamBuffer();
    }

    public function testConstructorWithDefaultMaxSize(): void
    {
        $buffer = new StreamBuffer();
        $stats = $buffer->getStats();

        $this->assertSame(1048576, $stats['max_buffer_size']); // 1MB
    }

    public function testConstructorWithCustomMaxSize(): void
    {
        $customSize = 2048;
        $buffer = new StreamBuffer($customSize);
        $stats = $buffer->getStats();

        $this->assertSame($customSize, $stats['max_buffer_size']);
    }

    public function testAddSendDataReturnsCorrectOffset(): void
    {
        $data1 = 'Hello';
        $data2 = 'World';

        $offset1 = $this->buffer->addSendData($data1);
        $offset2 = $this->buffer->addSendData($data2);

        $this->assertSame(0, $offset1);
        $this->assertSame(5, $offset2);
    }

    public function testGetSendDataWithSmallChunk(): void
    {
        $data = 'Hello World';
        $this->buffer->addSendData($data);

        $result = $this->buffer->getSendData(5);

        $this->assertNotNull($result);
        $this->assertSame(0, $result['offset']);
        $this->assertSame('Hello', $result['data']);
        $this->assertFalse($result['fin']);
    }

    public function testGetSendDataCompleteChunk(): void
    {
        $data = 'Hello';
        $this->buffer->addSendData($data);

        $result = $this->buffer->getSendData(10);

        $this->assertNotNull($result);
        $this->assertSame(0, $result['offset']);
        $this->assertSame('Hello', $result['data']);
        $this->assertTrue($result['fin']);
    }

    public function testGetSendDataEmptyBuffer(): void
    {
        $result = $this->buffer->getSendData(10);

        $this->assertNull($result);
    }

    public function testAddRecvDataSuccess(): void
    {
        $data = 'Hello';
        $offset = 0;

        $result = $this->buffer->addRecvData($data, $offset);

        $this->assertTrue($result);
    }

    public function testAddRecvDataExceedsLimit(): void
    {
        $buffer = new StreamBuffer(10); // 10字节限制
        $data = 'Hello World!'; // 12字节

        $result = $buffer->addRecvData($data, 0);

        $this->assertFalse($result);
    }

    public function testGetRecvDataContinuous(): void
    {
        $this->buffer->addRecvData('Hello', 0);
        $this->buffer->addRecvData(' World', 5);

        $result = $this->buffer->getRecvData();

        $this->assertSame('Hello World', $result);
    }

    public function testGetRecvDataWithGap(): void
    {
        $this->buffer->addRecvData('Hello', 0);
        $this->buffer->addRecvData('World', 10); // 有间隙

        $result = $this->buffer->getRecvData();

        $this->assertSame('Hello', $result);
    }

    public function testGetRecvDataEmptyBuffer(): void
    {
        $result = $this->buffer->getRecvData();

        $this->assertNull($result);
    }

    public function testHasSendData(): void
    {
        $this->assertFalse($this->buffer->hasSendData());

        $this->buffer->addSendData('Hello');

        $this->assertTrue($this->buffer->hasSendData());
    }

    public function testHasRecvData(): void
    {
        $this->assertFalse($this->buffer->hasRecvData());

        $this->buffer->addRecvData('Hello', 0);

        $this->assertTrue($this->buffer->hasRecvData());
    }

    public function testHasRecvDataWithGap(): void
    {
        $this->buffer->addRecvData('Hello', 10); // 不从0开始

        $this->assertFalse($this->buffer->hasRecvData());
    }

    public function testClearSendBuffer(): void
    {
        $this->buffer->addSendData('Hello');
        $this->assertTrue($this->buffer->hasSendData());

        $this->buffer->clearSendBuffer();

        $this->assertFalse($this->buffer->hasSendData());
    }

    public function testClearRecvBuffer(): void
    {
        $this->buffer->addRecvData('Hello', 0);
        $this->assertTrue($this->buffer->hasRecvData());

        $this->buffer->clearRecvBuffer();

        $this->assertFalse($this->buffer->hasRecvData());
    }

    public function testGetSendBufferSize(): void
    {
        $this->assertSame(0, $this->buffer->getSendBufferSize());

        $this->buffer->addSendData('Hello');
        $this->buffer->addSendData('World');

        $this->assertSame(10, $this->buffer->getSendBufferSize());
    }

    public function testGetRecvBufferSize(): void
    {
        $this->assertSame(0, $this->buffer->getRecvBufferSize());

        $this->buffer->addRecvData('Hello', 0);
        $this->buffer->addRecvData('World', 10);

        $this->assertSame(10, $this->buffer->getRecvBufferSize());
    }

    public function testIsNearFull(): void
    {
        $buffer = new StreamBuffer(100);

        $this->assertFalse($buffer->isNearFull());

        $buffer->addRecvData(str_repeat('x', 85), 0); // 85%

        $this->assertTrue($buffer->isNearFull());
    }

    public function testIsNearFullWithCustomThreshold(): void
    {
        $buffer = new StreamBuffer(100);
        $buffer->addRecvData(str_repeat('x', 60), 0); // 60%

        $this->assertFalse($buffer->isNearFull(0.8)); // 80%阈值
        $this->assertTrue($buffer->isNearFull(0.5));  // 50%阈值
    }

    public function testReset(): void
    {
        $this->buffer->addSendData('Hello');
        $this->buffer->addRecvData('World', 0);

        $this->buffer->reset();

        $stats = $this->buffer->getStats();
        $this->assertSame(0, $stats['send_buffer_chunks']);
        $this->assertSame(0, $stats['recv_buffer_chunks']);
        $this->assertSame(0, $stats['send_offset']);
        $this->assertSame(0, $stats['recv_offset']);
    }

    public function testGetStats(): void
    {
        $this->buffer->addSendData('Hello');
        $this->buffer->addRecvData('World', 0);

        $stats = $this->buffer->getStats();

        $this->assertArrayHasKey('send_buffer_chunks', $stats);
        $this->assertArrayHasKey('send_buffer_size', $stats);
        $this->assertArrayHasKey('recv_buffer_chunks', $stats);
        $this->assertArrayHasKey('recv_buffer_size', $stats);
        $this->assertArrayHasKey('send_offset', $stats);
        $this->assertArrayHasKey('recv_offset', $stats);
        $this->assertArrayHasKey('max_buffer_size', $stats);

        $this->assertSame(1, $stats['send_buffer_chunks']);
        $this->assertSame(5, $stats['send_buffer_size']);
        $this->assertSame(1, $stats['recv_buffer_chunks']);
        $this->assertSame(5, $stats['recv_buffer_size']);
        $this->assertSame(5, $stats['send_offset']);
        $this->assertSame(0, $stats['recv_offset']);
    }
}
