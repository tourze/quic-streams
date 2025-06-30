<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams;

use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Core\Enum\StreamRecvState;
use Tourze\QUIC\Core\Enum\StreamSendState;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\FlowControl\StreamFlowController;
use Tourze\QUIC\Streams\Exception\StreamException;

/**
 * QUIC 流基础类
 *
 * 实现QUIC协议中的流概念，管理流的生命周期、状态和数据传输
 * 参考：RFC 9000 Section 2 和 Section 3
 */
abstract class Stream
{
    private readonly int $id;
    private readonly StreamType $type;
    private StreamSendState $sendState = StreamSendState::READY;
    private StreamRecvState $recvState = StreamRecvState::RECV;
    
    protected string $sendBuffer = '';
    protected array $recvBuffer = [];
    protected int $nextExpectedOffset = 0;
    protected int $maxSendOffset = 0;
    protected bool $finSent = false;
    protected bool $finReceived = false;
    
    protected ?StreamFlowController $flowController = null;
    
    public function __construct(int $id, ?StreamFlowController $flowController = null)
    {
        $this->id = $id;
        $this->type = StreamType::fromStreamId($id);
        $this->flowController = $flowController;
    }
    
    /**
     * 获取流ID
     */
    public function getId(): int
    {
        return $this->id;
    }
    
    /**
     * 获取流类型
     */
    public function getType(): StreamType
    {
        return $this->type;
    }
    
    /**
     * 获取发送状态
     */
    public function getSendState(): StreamSendState
    {
        return $this->sendState;
    }
    
    /**
     * 获取接收状态
     */
    public function getRecvState(): StreamRecvState
    {
        return $this->recvState;
    }
    
    /**
     * 发送数据
     *
     * @param string $data 要发送的数据
     * @param bool $fin 是否为最后一帧
     * @throws StreamException 流状态错误时抛出
     */
    public function send(string $data, bool $fin = false): void
    {
        if (!$this->canSend()) {
            throw new StreamException('Stream not ready for sending', QuicError::STREAM_STATE_ERROR);
        }
        
        $this->sendBuffer .= $data;
        $this->finSent = $fin;
        
        if ($this->sendState === StreamSendState::READY) {
            $this->sendState = StreamSendState::SEND;
        }
        
        $this->processSendBuffer();
    }
    
    /**
     * 接收数据
     *
     * @param string $data 接收的数据
     * @param int $offset 数据偏移量
     * @param bool $fin 是否为最后一帧
     * @throws StreamException 流状态错误时抛出
     */
    public function receive(string $data, int $offset, bool $fin = false): void
    {
        if (!$this->canReceive()) {
            throw new StreamException('Stream not ready for receiving', QuicError::STREAM_STATE_ERROR);
        }
        
        // 检查流控制
        if ($this->flowController !== null && !$this->flowController->canReceive(strlen($data))) {
            throw new StreamException('Stream data limit exceeded', QuicError::FLOW_CONTROL_ERROR);
        }
        
        // 存储数据
        $this->recvBuffer[$offset] = $data;
        
        if ($fin) {
            $this->finReceived = true;
            if ($this->recvState === StreamRecvState::RECV) {
                $this->recvState = StreamRecvState::SIZE_KNOWN;
            }
        }
        
        $this->processRecvBuffer();
    }
    
    /**
     * 重置流
     */
    public function reset(): void
    {
        $this->sendState = StreamSendState::RESET_SENT;
        $this->sendBuffer = '';
    }
    
    /**
     * 处理流重置
     */
    public function handleReset(): void
    {
        $this->sendState = StreamSendState::RESET_RECVD;
        $this->recvState = StreamRecvState::RESET_RECVD;
        $this->sendBuffer = '';
        $this->recvBuffer = [];
    }
    
    /**
     * 是否可以发送数据
     */
    protected function canSend(): bool
    {
        return match ($this->sendState) {
            StreamSendState::READY, StreamSendState::SEND => true,
            default => false,
        };
    }
    
    /**
     * 是否可以接收数据
     */
    protected function canReceive(): bool
    {
        return match ($this->recvState) {
            StreamRecvState::RECV, StreamRecvState::SIZE_KNOWN => true,
            default => false,
        };
    }
    
    /**
     * 处理发送缓冲区
     */
    protected function processSendBuffer(): void
    {
        if (empty($this->sendBuffer) && $this->finSent) {
            $this->sendState = StreamSendState::DATA_SENT;
        }
    }
    
    /**
     * 处理接收缓冲区
     */
    protected function processRecvBuffer(): void
    {
        // 按序处理接收的数据
        ksort($this->recvBuffer);
        $processedData = '';
        
        foreach ($this->recvBuffer as $offset => $data) {
            if ($offset === $this->nextExpectedOffset) {
                $processedData .= $data;
                $this->nextExpectedOffset += strlen($data);
                unset($this->recvBuffer[$offset]);
            } else {
                break; // 遇到乱序数据，暂停处理
            }
        }
        
        if (!empty($processedData)) {
            $this->onDataReceived($processedData);
        }
        
        // 检查是否完成接收
        if ($this->finReceived && empty($this->recvBuffer)) {
            $this->recvState = StreamRecvState::DATA_RECVD;
            $this->onStreamCompleted();
        }
    }
    
    /**
     * 数据接收回调 - 由子类实现
     */
    abstract protected function onDataReceived(string $data): void;
    
    /**
     * 流完成回调 - 由子类实现
     */
    abstract protected function onStreamCompleted(): void;
}
