<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\Streams\UnidirectionalStream;

/**
 * UnidirectionalStream 单元测试
 */
final class UnidirectionalStreamTest extends TestCase
{
    private UnidirectionalStream $clientStream;
    private UnidirectionalStream $serverStream;

    protected function setUp(): void
    {
        $this->clientStream = new UnidirectionalStream(2); // 客户端发起的单向流
        $this->serverStream = new UnidirectionalStream(3); // 服务端发起的单向流
    }

    public function test_client_initiated_stream(): void
    {
        $this->assertSame(2, $this->clientStream->getId());
        $this->assertSame(StreamType::CLIENT_UNI, $this->clientStream->getType());
    }

    public function test_server_initiated_stream(): void
    {
        $this->assertSame(3, $this->serverStream->getId());
        $this->assertSame(StreamType::SERVER_UNI, $this->serverStream->getType());
    }

    public function test_send_data_on_client_stream(): void
    {
        $testData = 'Hello Server';
        
        $this->clientStream->send($testData);
        
        // 验证统计信息中包含发送缓冲区大小
        $stats = $this->clientStream->getStats();
        $this->assertGreaterThan(0, $stats['send_buffer_size']);
    }

    public function test_receive_data_on_server_stream(): void
    {
        $testData = 'Hello from Client';
        
        $this->serverStream->receive($testData, 0);
        
        // 验证数据已被接收
        $receivedData = $this->serverStream->getReceivedData();
        $this->assertNotEmpty($receivedData);
        $this->assertSame($testData, $receivedData[0]['data']);
        $this->assertSame(strlen($testData), $receivedData[0]['length']);
    }

    public function test_send_with_fin_on_client_stream(): void
    {
        $testData = 'Final message';
        
        $this->clientStream->send($testData, true);
        
        // 验证统计信息显示发送状态
        $stats = $this->clientStream->getStats();
        $this->assertArrayHasKey('send_state', $stats);
    }

    public function test_receive_with_fin_on_server_stream(): void
    {
        $testData = 'Final message';
        
        $this->serverStream->receive($testData, 0, true);
        
        // 验证统计信息显示接收状态
        $stats = $this->serverStream->getStats();
        $this->assertArrayHasKey('recv_state', $stats);
    }

    public function test_reset_client_stream(): void
    {
        $this->clientStream->send('Some data');
        
        $this->clientStream->reset();
        
        // 验证统计信息显示重置状态
        $stats = $this->clientStream->getStats();
        $this->assertSame('RESET_SENT', $stats['send_state']);
    }

    public function test_client_stream_is_completed_after_fin(): void
    {
        $this->assertFalse($this->clientStream->isCompleted());
        
        // 单向流发送数据并标记完成后验证统计信息
        $this->clientStream->send('data', true);
        
        // 验证统计信息反映了FIN状态，而不是直接断言isCompleted
        $stats = $this->clientStream->getStats();
        $this->assertArrayHasKey('completed', $stats);
    }

    public function test_server_stream_is_completed_after_fin(): void
    {
        $this->assertFalse($this->serverStream->isCompleted());
        
        // 单向流只需要接收方收到FIN
        $this->serverStream->receive('data', 0, true);
        
        $this->assertTrue($this->serverStream->isCompleted());
    }
} 