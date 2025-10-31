<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests;

use Tourze\QUIC\Streams\Stream;

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
