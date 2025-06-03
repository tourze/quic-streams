<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams;

use Tourze\QUIC\Core\Enum\QuicError;

/**
 * 流异常类
 * 
 * 处理QUIC流操作中的异常情况
 */
class StreamException extends \Exception
{
    private readonly QuicError $quicError;

    public function __construct(string $message, QuicError $quicError, ?\Throwable $previous = null)
    {
        $this->quicError = $quicError;
        parent::__construct($message, $quicError->value, $previous);
    }

    /**
     * 获取QUIC错误码
     */
    public function getQuicError(): QuicError
    {
        return $this->quicError;
    }
} 