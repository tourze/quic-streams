<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams;

use Tourze\QUIC\Core\Enum\StreamRecvState;
use Tourze\QUIC\Core\Enum\StreamSendState;

/**
 * 流状态机
 * 
 * 管理QUIC流的状态转换
 * 参考：RFC 9000 Section 3
 */
class StreamStateMachine
{
    private StreamSendState $sendState = StreamSendState::READY;
    private StreamRecvState $recvState = StreamRecvState::RECV;

    /**
     * 尝试发送状态转换
     */
    public function transitionSend(StreamSendState $newState): bool
    {
        return match ([$this->sendState, $newState]) {
            // READY状态可以转换到SEND或RESET_SENT
            [StreamSendState::READY, StreamSendState::SEND] => $this->setSendState($newState),
            [StreamSendState::READY, StreamSendState::RESET_SENT] => $this->setSendState($newState),

            // SEND状态可以转换到DATA_SENT、RESET_SENT或RESET_RECVD
            [StreamSendState::SEND, StreamSendState::DATA_SENT] => $this->setSendState($newState),
            [StreamSendState::SEND, StreamSendState::RESET_SENT] => $this->setSendState($newState),
            [StreamSendState::SEND, StreamSendState::RESET_RECVD] => $this->setSendState($newState),

            // DATA_SENT状态可以转换到RESET_RECVD
            [StreamSendState::DATA_SENT, StreamSendState::RESET_RECVD] => $this->setSendState($newState),

            // RESET_SENT状态可以转换到RESET_RECVD
            [StreamSendState::RESET_SENT, StreamSendState::RESET_RECVD] => $this->setSendState($newState),

            // 相同状态保持不变
            default => $this->sendState === $newState,
        };
    }

    /**
     * 尝试接收状态转换
     */
    public function transitionRecv(StreamRecvState $newState): bool
    {
        return match ([$this->recvState, $newState]) {
            // RECV状态可以转换到SIZE_KNOWN或RESET_RECVD
            [StreamRecvState::RECV, StreamRecvState::SIZE_KNOWN] => $this->setRecvState($newState),
            [StreamRecvState::RECV, StreamRecvState::RESET_RECVD] => $this->setRecvState($newState),

            // SIZE_KNOWN状态可以转换到DATA_RECVD或RESET_RECVD
            [StreamRecvState::SIZE_KNOWN, StreamRecvState::DATA_RECVD] => $this->setRecvState($newState),
            [StreamRecvState::SIZE_KNOWN, StreamRecvState::RESET_RECVD] => $this->setRecvState($newState),

            // 相同状态保持不变
            default => $this->recvState === $newState,
        };
    }

    /**
     * 检查是否可以发送数据
     */
    public function canSend(): bool
    {
        return match ($this->sendState) {
            StreamSendState::READY, StreamSendState::SEND => true,
            default => false,
        };
    }

    /**
     * 检查是否可以接收数据
     */
    public function canReceive(): bool
    {
        return match ($this->recvState) {
            StreamRecvState::RECV, StreamRecvState::SIZE_KNOWN => true,
            default => false,
        };
    }

    /**
     * 检查流是否已关闭
     */
    public function isClosed(): bool
    {
        $sendClosed = match ($this->sendState) {
            StreamSendState::DATA_SENT, StreamSendState::RESET_SENT, StreamSendState::RESET_RECVD => true,
            default => false,
        };

        $recvClosed = match ($this->recvState) {
            StreamRecvState::DATA_RECVD, StreamRecvState::RESET_RECVD => true,
            default => false,
        };

        return $sendClosed && $recvClosed;
    }

    /**
     * 检查流是否可以被垃圾回收
     */
    public function canGarbageCollect(): bool
    {
        return $this->isClosed();
    }

    /**
     * 获取当前发送状态
     */
    public function getSendState(): StreamSendState
    {
        return $this->sendState;
    }

    /**
     * 获取当前接收状态
     */
    public function getRecvState(): StreamRecvState
    {
        return $this->recvState;
    }

    /**
     * 重置流状态
     */
    public function reset(): void
    {
        $this->sendState = StreamSendState::RESET_SENT;
        $this->recvState = StreamRecvState::RESET_RECVD;
    }

    /**
     * 获取状态摘要
     */
    public function getStateSummary(): array
    {
        return [
            'send_state' => $this->sendState->name,
            'recv_state' => $this->recvState->name,
            'can_send' => $this->canSend(),
            'can_receive' => $this->canReceive(),
            'is_closed' => $this->isClosed(),
            'can_gc' => $this->canGarbageCollect(),
        ];
    }

    /**
     * 设置发送状态
     */
    private function setSendState(StreamSendState $state): bool
    {
        $this->sendState = $state;
        return true;
    }

    /**
     * 设置接收状态
     */
    private function setRecvState(StreamRecvState $state): bool
    {
        $this->recvState = $state;
        return true;
    }
} 