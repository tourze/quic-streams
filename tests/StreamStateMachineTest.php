<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\StreamRecvState;
use Tourze\QUIC\Core\Enum\StreamSendState;
use Tourze\QUIC\Streams\StreamStateMachine;

/**
 * StreamStateMachine 单元测试
 *
 * @internal
 */
#[CoversClass(StreamStateMachine::class)]
final class StreamStateMachineTest extends TestCase
{
    private StreamStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = new StreamStateMachine();
    }

    public function testInitialStates(): void
    {
        $this->assertSame(StreamSendState::READY, $this->stateMachine->getSendState());
        $this->assertSame(StreamRecvState::RECV, $this->stateMachine->getRecvState());
    }

    public function testCanSendInInitialState(): void
    {
        $this->assertTrue($this->stateMachine->canSend());
    }

    public function testCanReceiveInInitialState(): void
    {
        $this->assertTrue($this->stateMachine->canReceive());
    }

    public function testIsNotClosedInitially(): void
    {
        $this->assertFalse($this->stateMachine->isClosed());
    }

    public function testCannotGarbageCollectInitially(): void
    {
        $this->assertFalse($this->stateMachine->canGarbageCollect());
    }

    public function testTransitionSendReadyToSend(): void
    {
        $result = $this->stateMachine->transitionSend(StreamSendState::SEND);

        $this->assertTrue($result);
        $this->assertSame(StreamSendState::SEND, $this->stateMachine->getSendState());
    }

    public function testTransitionSendReadyToResetSent(): void
    {
        $result = $this->stateMachine->transitionSend(StreamSendState::RESET_SENT);

        $this->assertTrue($result);
        $this->assertSame(StreamSendState::RESET_SENT, $this->stateMachine->getSendState());
    }

    public function testTransitionSendToDataSent(): void
    {
        $this->stateMachine->transitionSend(StreamSendState::SEND);

        $result = $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);

        $this->assertTrue($result);
        $this->assertSame(StreamSendState::DATA_SENT, $this->stateMachine->getSendState());
    }

    public function testTransitionRecvToSizeKnown(): void
    {
        $result = $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);

        $this->assertTrue($result);
        $this->assertSame(StreamRecvState::SIZE_KNOWN, $this->stateMachine->getRecvState());
    }

    public function testTransitionRecvSizeKnownToDataRecvd(): void
    {
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);

        $result = $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);

        $this->assertTrue($result);
        $this->assertSame(StreamRecvState::DATA_RECVD, $this->stateMachine->getRecvState());
    }

    public function testCannotSendAfterDataSent(): void
    {
        $this->stateMachine->transitionSend(StreamSendState::SEND);
        $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);

        $this->assertFalse($this->stateMachine->canSend());
    }

    public function testCannotReceiveAfterDataRecvd(): void
    {
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);

        $this->assertFalse($this->stateMachine->canReceive());
    }

    public function testIsClosedWhenBothDataComplete(): void
    {
        $this->stateMachine->transitionSend(StreamSendState::SEND);
        $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);

        $this->assertTrue($this->stateMachine->isClosed());
        $this->assertTrue($this->stateMachine->canGarbageCollect());
    }

    public function testResetSetsResetStates(): void
    {
        $this->stateMachine->reset();

        $this->assertSame(StreamSendState::RESET_SENT, $this->stateMachine->getSendState());
        $this->assertSame(StreamRecvState::RESET_RECVD, $this->stateMachine->getRecvState());
    }

    public function testResetMakesStreamClosed(): void
    {
        $this->stateMachine->reset();

        $this->assertTrue($this->stateMachine->isClosed());
        $this->assertTrue($this->stateMachine->canGarbageCollect());
    }

    public function testGetStateSummary(): void
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

    public function testInvalidSendTransitionKeepsCurrentState(): void
    {
        // 尝试从READY直接跳到DATA_SENT（无效）
        $result = $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);

        $this->assertFalse($result);
        $this->assertSame(StreamSendState::READY, $this->stateMachine->getSendState());
    }

    public function testInvalidRecvTransitionKeepsCurrentState(): void
    {
        // 尝试从RECV直接跳到DATA_RECVD（无效）
        $result = $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);

        $this->assertFalse($result);
        $this->assertSame(StreamRecvState::RECV, $this->stateMachine->getRecvState());
    }

    public function testSameStateTransitionReturnsTrue(): void
    {
        $result = $this->stateMachine->transitionSend(StreamSendState::READY);

        $this->assertTrue($result);
        $this->assertSame(StreamSendState::READY, $this->stateMachine->getSendState());
    }

    public function testCanGarbageCollect(): void
    {
        // 初始状态下不能垃圾回收
        $this->assertFalse($this->stateMachine->canGarbageCollect());

        // 将流状态转换为已关闭
        $this->stateMachine->transitionSend(StreamSendState::SEND);
        $this->stateMachine->transitionSend(StreamSendState::DATA_SENT);
        $this->stateMachine->transitionRecv(StreamRecvState::SIZE_KNOWN);
        $this->stateMachine->transitionRecv(StreamRecvState::DATA_RECVD);

        // 现在应该可以垃圾回收了
        $this->assertTrue($this->stateMachine->canGarbageCollect());

        // 重置状态机
        $this->stateMachine->reset();

        // 重置后也应该可以垃圾回收
        $this->assertTrue($this->stateMachine->canGarbageCollect());
    }
}
