<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams;

use Tourze\QUIC\Core\Constants;
use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Core\Enum\StreamType;
use Tourze\QUIC\FlowControl\FlowControlManager;

/**
 * 流管理器
 * 
 * 管理QUIC连接中的所有流，包括创建、销毁和状态管理
 * 参考：RFC 9000 Section 2 和 Section 19
 */
class StreamManager
{
    /** @var array<int, Stream> */
    private array $streams = [];
    
    /** @var array<int, int> 下一个可用的流ID */
    private array $nextStreamIds = [];
    
    /** @var array<int, int> 最大流数量限制 */
    private array $maxStreams = [];
    
    private bool $isServer;
    private ?FlowControlManager $flowControlManager;

    public function __construct(bool $isServer = false, ?FlowControlManager $flowControlManager = null)
    {
        $this->isServer = $isServer;
        $this->flowControlManager = $flowControlManager;
        
        // 初始化流ID
        $this->initializeStreamIds();
        
        // 设置默认流限制
        $this->maxStreams = [
            StreamType::CLIENT_BIDI->value => Constants::DEFAULT_MAX_STREAMS_BIDI,
            StreamType::SERVER_BIDI->value => Constants::DEFAULT_MAX_STREAMS_BIDI,
            StreamType::CLIENT_UNI->value => Constants::DEFAULT_MAX_STREAMS_UNI,
            StreamType::SERVER_UNI->value => Constants::DEFAULT_MAX_STREAMS_UNI,
        ];
    }

    /**
     * 创建新流
     */
    public function createStream(StreamType $type): Stream
    {
        $streamId = $this->getNextStreamId($type);
        
        // 检查流数量限制
        if ($this->getStreamCount($type) >= $this->maxStreams[$type->value]) {
            throw new StreamException('Stream limit exceeded', QuicError::STREAM_LIMIT_ERROR);
        }

        $flowController = $this->flowControlManager?->createStream($streamId);
        
        $stream = match ($type) {
            StreamType::CLIENT_BIDI, StreamType::SERVER_BIDI => 
                new BidirectionalStream($streamId, $flowController),
            StreamType::CLIENT_UNI, StreamType::SERVER_UNI => 
                new UnidirectionalStream($streamId, $flowController),
        };

        $this->streams[$streamId] = $stream;
        return $stream;
    }

    /**
     * 获取流
     */
    public function getStream(int $streamId): ?Stream
    {
        return $this->streams[$streamId] ?? null;
    }

    /**
     * 获取或创建流
     */
    public function getOrCreateStream(int $streamId): Stream
    {
        if (isset($this->streams[$streamId])) {
            return $this->streams[$streamId];
        }

        $type = StreamType::fromStreamId($streamId);
        
        // 验证流ID是否有效
        if (!$this->isValidStreamId($streamId, $type)) {
            throw new StreamException('Invalid stream ID', QuicError::STREAM_STATE_ERROR);
        }

        $flowController = $this->flowControlManager?->createStream($streamId);
        
        $stream = match ($type) {
            StreamType::CLIENT_BIDI, StreamType::SERVER_BIDI => 
                new BidirectionalStream($streamId, $flowController),
            StreamType::CLIENT_UNI, StreamType::SERVER_UNI => 
                new UnidirectionalStream($streamId, $flowController),
        };

        $this->streams[$streamId] = $stream;
        return $stream;
    }

    /**
     * 删除流
     */
    public function removeStream(int $streamId): bool
    {
        if (isset($this->streams[$streamId])) {
            // 清理流控制器
            $this->flowControlManager?->closeStream($streamId);
            unset($this->streams[$streamId]);
            return true;
        }
        return false;
    }

    /**
     * 获取所有流
     */
    public function getAllStreams(): array
    {
        return $this->streams;
    }

    /**
     * 获取指定类型的流
     */
    public function getStreamsByType(StreamType $type): array
    {
        return array_filter($this->streams, fn(Stream $stream) => $stream->getType() === $type);
    }

    /**
     * 获取流数量
     */
    public function getStreamCount(?StreamType $type = null): int
    {
        if ($type === null) {
            return count($this->streams);
        }
        
        return count($this->getStreamsByType($type));
    }

    /**
     * 设置最大流数量
     */
    public function setMaxStreams(StreamType $type, int $maxStreams): void
    {
        $this->maxStreams[$type->value] = $maxStreams;
    }

    /**
     * 获取最大流数量
     */
    public function getMaxStreams(StreamType $type): int
    {
        return $this->maxStreams[$type->value];
    }

    /**
     * 清理已关闭的流
     */
    public function garbageCollect(): int
    {
        $removedCount = 0;
        
        foreach ($this->streams as $streamId => $stream) {
            // 检查流是否可以被清理
            $canGC = match (true) {
                $stream instanceof BidirectionalStream => $stream->isCompleted(),
                $stream instanceof UnidirectionalStream => $stream->isCompleted(),
                default => false,
            };
            
            if ($canGC) {
                $this->flowControlManager?->closeStream($streamId);
                unset($this->streams[$streamId]);
                $removedCount++;
            }
        }
        
        return $removedCount;
    }

    /**
     * 获取管理器统计信息
     */
    public function getStats(): array
    {
        $stats = [
            'total_streams' => count($this->streams),
            'streams_by_type' => [],
            'next_stream_ids' => $this->nextStreamIds,
            'max_streams' => $this->maxStreams,
            'is_server' => $this->isServer,
        ];

        foreach (StreamType::cases() as $type) {
            $stats['streams_by_type'][$type->name] = $this->getStreamCount($type);
        }

        return $stats;
    }

    /**
     * 重置所有流
     */
    public function reset(): void
    {
        foreach ($this->streams as $streamId => $stream) {
            $stream->reset();
            $this->flowControlManager?->closeStream($streamId);
        }
        $this->streams = [];
        $this->initializeStreamIds();
    }

    /**
     * 初始化流ID
     */
    private function initializeStreamIds(): void
    {
        if ($this->isServer) {
            $this->nextStreamIds = [
                StreamType::CLIENT_BIDI->value => 0,  // 客户端发起的双向流
                StreamType::SERVER_BIDI->value => 1,  // 服务器发起的双向流
                StreamType::CLIENT_UNI->value => 2,   // 客户端发起的单向流
                StreamType::SERVER_UNI->value => 3,   // 服务器发起的单向流
            ];
        } else {
            $this->nextStreamIds = [
                StreamType::CLIENT_BIDI->value => 0,  // 客户端发起的双向流
                StreamType::SERVER_BIDI->value => 1,  // 服务器发起的双向流  
                StreamType::CLIENT_UNI->value => 2,   // 客户端发起的单向流
                StreamType::SERVER_UNI->value => 3,   // 服务器发起的单向流
            ];
        }
    }

    /**
     * 获取下一个流ID
     */
    private function getNextStreamId(StreamType $type): int
    {
        $streamId = $this->nextStreamIds[$type->value];
        $this->nextStreamIds[$type->value] += 4; // 流ID间隔为4
        return $streamId;
    }

    /**
     * 验证流ID是否有效
     */
    private function isValidStreamId(int $streamId, StreamType $type): bool
    {
        // 检查流ID是否符合类型规范
        $expectedMod = $type->value;
        if (($streamId % 4) !== $expectedMod) {
            return false;
        }

        // 检查流ID是否在有效范围内
        return $streamId >= 0 && $streamId <= Constants::MAX_STREAM_ID;
    }
} 