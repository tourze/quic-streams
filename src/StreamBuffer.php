<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams;

/**
 * 流数据缓冲区
 * 
 * 管理流的发送和接收缓冲区，处理数据的排序和组装
 */
class StreamBuffer
{
    private array $sendBuffer = [];
    private array $recvBuffer = [];
    private int $sendOffset = 0;
    private int $recvOffset = 0;
    private int $maxBufferSize;

    public function __construct(int $maxBufferSize = 1048576) // 1MB
    {
        $this->maxBufferSize = $maxBufferSize;
    }

    /**
     * 添加发送数据
     */
    public function addSendData(string $data): int
    {
        $offset = $this->sendOffset;
        $this->sendBuffer[$offset] = $data;
        $this->sendOffset += strlen($data);
        
        return $offset;
    }

    /**
     * 获取发送数据
     */
    public function getSendData(int $maxLength): ?array
    {
        if (empty($this->sendBuffer)) {
            return null;
        }

        // 获取最早的数据
        ksort($this->sendBuffer);
        $offset = array_key_first($this->sendBuffer);
        $data = $this->sendBuffer[$offset];
        
        if (strlen($data) > $maxLength) {
            // 分片处理
            $chunk = substr($data, 0, $maxLength);
            $this->sendBuffer[$offset] = substr($data, $maxLength);
            
            return [
                'offset' => $offset,
                'data' => $chunk,
                'fin' => false,
            ];
        } else {
            // 完整数据
            unset($this->sendBuffer[$offset]);
            
            return [
                'offset' => $offset,
                'data' => $data,
                'fin' => empty($this->sendBuffer),
            ];
        }
    }

    /**
     * 添加接收数据
     */
    public function addRecvData(string $data, int $offset): bool
    {
        // 检查缓冲区大小限制
        $currentBufferSize = $this->getRecvBufferSize();
        if ($currentBufferSize + strlen($data) > $this->maxBufferSize) {
            return false;
        }

        $this->recvBuffer[$offset] = $data;
        return true;
    }

    /**
     * 获取连续的接收数据
     */
    public function getRecvData(): ?string
    {
        if (empty($this->recvBuffer)) {
            return null;
        }

        ksort($this->recvBuffer);
        $result = '';
        
        foreach ($this->recvBuffer as $offset => $data) {
            if ($offset === $this->recvOffset) {
                $result .= $data;
                $this->recvOffset += strlen($data);
                unset($this->recvBuffer[$offset]);
            } else {
                break; // 遇到空隙，停止处理
            }
        }

        return empty($result) ? null : $result;
    }

    /**
     * 检查是否有发送数据
     */
    public function hasSendData(): bool
    {
        return !empty($this->sendBuffer);
    }

    /**
     * 检查是否有接收数据可处理
     */
    public function hasRecvData(): bool
    {
        if (empty($this->recvBuffer)) {
            return false;
        }

        ksort($this->recvBuffer);
        $firstOffset = array_key_first($this->recvBuffer);
        
        return $firstOffset === $this->recvOffset;
    }

    /**
     * 清空发送缓冲区
     */
    public function clearSendBuffer(): void
    {
        $this->sendBuffer = [];
    }

    /**
     * 清空接收缓冲区
     */
    public function clearRecvBuffer(): void
    {
        $this->recvBuffer = [];
        $this->recvOffset = 0;
    }

    /**
     * 获取发送缓冲区大小
     */
    public function getSendBufferSize(): int
    {
        return array_sum(array_map('strlen', $this->sendBuffer));
    }

    /**
     * 获取接收缓冲区大小
     */
    public function getRecvBufferSize(): int
    {
        return array_sum(array_map('strlen', $this->recvBuffer));
    }

    /**
     * 获取缓冲区统计信息
     */
    public function getStats(): array
    {
        return [
            'send_buffer_chunks' => count($this->sendBuffer),
            'send_buffer_size' => $this->getSendBufferSize(),
            'recv_buffer_chunks' => count($this->recvBuffer),
            'recv_buffer_size' => $this->getRecvBufferSize(),
            'send_offset' => $this->sendOffset,
            'recv_offset' => $this->recvOffset,
            'max_buffer_size' => $this->maxBufferSize,
        ];
    }

    /**
     * 检查缓冲区是否接近满
     */
    public function isNearFull(float $threshold = 0.8): bool
    {
        $currentSize = $this->getRecvBufferSize();
        return ($currentSize / $this->maxBufferSize) >= $threshold;
    }

    /**
     * 重置缓冲区
     */
    public function reset(): void
    {
        $this->sendBuffer = [];
        $this->recvBuffer = [];
        $this->sendOffset = 0;
        $this->recvOffset = 0;
    }
} 