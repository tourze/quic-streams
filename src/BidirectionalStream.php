<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams;

use Tourze\QUIC\FlowControl\StreamFlowControl;

/**
 * 双向流实现
 *
 * 支持发送和接收数据的QUIC流
 * 双向流ID为4n或4n+1的形式
 */
class BidirectionalStream extends Stream
{
    /** @var array<int, array{data: string, timestamp: float, length: int}> */
    private array $receivedDataBuffer = [];

    private bool $streamCompleted = false;

    public function __construct(int $id, ?StreamFlowControl $flowController = null)
    {
        parent::__construct($id, $flowController);
    }

    /**
     * 获取接收的数据缓冲区
     * @return array<int, array{data: string, timestamp: float, length: int}>
     */
    public function getReceivedData(): array
    {
        return $this->receivedDataBuffer;
    }

    /**
     * 是否完成
     */
    public function isCompleted(): bool
    {
        return $this->streamCompleted;
    }

    /**
     * 清空接收缓冲区
     */
    public function clearReceivedData(): void
    {
        $this->receivedDataBuffer = [];
    }

    /**
     * 数据接收回调
     */
    protected function onDataReceived(string $data): void
    {
        $this->receivedDataBuffer[] = [
            'data' => $data,
            'timestamp' => microtime(true),
            'length' => strlen($data),
        ];
    }

    /**
     * 流完成回调
     */
    protected function onStreamCompleted(): void
    {
        $this->streamCompleted = true;
    }

    /**
     * 获取统计信息
     * @return array{id: int, type: string, send_state: string, recv_state: string, total_received: int, send_buffer_size: int, received_chunks: int, completed: bool}
     */
    public function getStats(): array
    {
        $totalReceived = array_sum(array_column($this->receivedDataBuffer, 'length'));
        $sendBufferSize = strlen($this->sendBuffer);

        return [
            'id' => $this->getId(),
            'type' => 'bidirectional',
            'send_state' => $this->getSendState()->name,
            'recv_state' => $this->getRecvState()->name,
            'total_received' => $totalReceived,
            'send_buffer_size' => $sendBufferSize,
            'received_chunks' => count($this->receivedDataBuffer),
            'completed' => $this->streamCompleted,
        ];
    }
}
