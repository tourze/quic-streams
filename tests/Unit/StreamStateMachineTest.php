<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\StreamRecvState;
use Tourze\QUIC\Core\Enum\StreamSendState;
use Tourze\QUIC\Streams\StreamStateMachine;

/**
 * StreamStateMachine 单元测试
 */
final class StreamStateMachineTest extends TestCase
{
    private StreamStateMachine $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = new StreamStateMachine();
    }

    public function test_initial_states(): void
    {
        $this->assertSame(StreamSendState::READY, $this->stateMachine->getSendState());
        $this->assertSame(StreamRecvState::RECV, $this->stateMachine->getRecvState());
    }

    public function test_can_send_in_initial_state(): void
    {
        $this->assertTrue($this->stateMachine->canSend());
    }

    public function test_can_receive_in_initial_state(): void
    {
        $this->assertTrue($this->stateMachine->canReceive());
    }

    public function test_is_not_closed_initially(): void
    {
        $this->assertFalse($this->stateMachine->isClosed());
    }

    public function test_cannot_garbage_collect_initially(): void
    {
        $this->assertFalse($this->stateMachine->canGarbageCollect());
    }

    public function test_transition_send_ready_to_send(): void
    {
        $result = $this->stateMachine->transitionSend(StreamSendState::SEND);
        
        $this->assertTrue($result);
        $this->assertSame(StreamSendState::SEND, $this->stateMachine->getSendState());
    }

    public function test_transition_send_ready_to_reset_sent(): void
    {
        $result = $this->stateMachine->transitionSend(StreamSendState::RESET_SENT);
        
        $this->assertTrue($result);
        $this->assertSame(StreamSendState::RESET_SENT, $this->stateMachine->getSendState());
    }

    public function test_transition_send_to_data_sent(): void
    {
        $this->stateMachine->transitionSend(StreamSendState::SEND);
        
        $result = $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);
        
        $this->assertTrue($result);
        $this->assertSame(StreamSendState::DATA_SENT, $this->stateMachine->getSendState());
    }

    public function test_transition_recv_to_size_known(): void
    {
        $result = $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        
        $this->assertTrue($result);
        $this->assertSame(StreamRecvState::SIZE_KNOWN, $this->stateMachine->getRecvState());
    }

    public function test_transition_recv_size_known_to_data_recvd(): void
    {
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        
        $result = $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);
        
        $this->assertTrue($result);
        $this->assertSame(StreamRecvState::DATA_RECVD, $this->stateMachine->getRecvState());
    }

    public function test_cannot_send_after_data_sent(): void
    {
        $this->stateMachine->transitionSend(StreamSendState::SEND);
        $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);
        
        $this->assertFalse($this->stateMachine->canSend());
    }

    public function test_cannot_receive_after_data_recvd(): void
    {
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);
        
        $this->assertFalse($this->stateMachine->canReceive());
    }

    public function test_is_closed_when_both_data_complete(): void
    {
        $this->stateMachine->transitionSend(StreamSendState::SEND);
        $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);
        
        $this->assertTrue($this->stateMachine->isClosed());
        $this->assertTrue($this->stateMachine->canGarbageCollect());
    }

    public function test_reset_sets_reset_states(): void
    {
        $this->stateMachine->reset();
        
        $this->assertSame(StreamSendState::RESET_SENT, $this->stateMachine->getSendState());
        $this->assertSame(StreamRecvState::RESET_RECVD, $this->stateMachine->getRecvState());
    }

    public function test_reset_makes_stream_closed(): void
    {
        $this->stateMachine->reset();
        
        $this->assertTrue($this->stateMachine->isClosed());
        $this->assertTrue($this->stateMachine->canGarbageCollect());
    }

    public function test_get_state_summary(): void
    {
        $summary = $this->stateMachine->getStateSummary();
        
        $this->assertArrayHasKey('send_state', $summary);
        $this->assertArrayHasKey('recv_state', $summary);
        $this->assertArrayHasKey('can_send', $summary);
        $this->assertArrayHasKey('can_receive', $summary);
        $this->assertArrayHasKey('is_closed', $summary);
        $this->assertArrayHasKey('can_gc', $summary);
        
        $this->assertSame('READY', $summary['send_state']);
        $this->assertSame('RECV', $summary['recv_state']);
        $this->assertTrue($summary['can_send']);
        $this->assertTrue($summary['can_receive']);
        $this->assertFalse($summary['is_closed']);
        $this->assertFalse($summary['can_gc']);
    }

    public function test_invalid_send_transition_keeps_current_state(): void
    {
        // 尝试从READY直接跳到DATA_SENT（无效）
        $result = $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);
        
        $this->assertFalse($result);
        $this->assertSame(StreamSendState::READY, $this->stateMachine->getSendState());
    }

    public function test_invalid_recv_transition_keeps_current_state(): void
    {
        // 尝试从RECV直接跳到DATA_RECVD（无效）
        $result = $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);
        
        $this->assertFalse($result);
        $this->assertSame(StreamRecvState::RECV, $this->stateMachine->getRecvState());
    }

    public function test_same_state_transition_returns_true(): void
    {
        $result = $this->stateMachine->transitionSend(StreamSendState::READY);
        
        $this->assertTrue($result);
        $this->assertSame(StreamSendState::READY, $this->stateMachine->getSendState());
    }
} 