<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Streams\StreamException;

/**
 * StreamException 单元测试
 */
final class StreamExceptionTest extends TestCase
{
    public function test_constructor_with_basic_parameters(): void
    {
        $message = 'Test stream error';
        $quicError = QuicError::STREAM_STATE_ERROR;
        
        $exception = new StreamException($message, $quicError);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($quicError->value, $exception->getCode());
        $this->assertSame($quicError, $exception->getQuicError());
    }

    public function test_constructor_with_previous_exception(): void
    {
        $message = 'Test stream error';
        $quicError = QuicError::FLOW_CONTROL_ERROR;
        $previous = new \RuntimeException('Previous error');
        
        $exception = new StreamException($message, $quicError, $previous);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($quicError->value, $exception->getCode());
        $this->assertSame($quicError, $exception->getQuicError());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_get_quic_error_returns_correct_error(): void
    {
        $quicError = QuicError::INTERNAL_ERROR;
        $exception = new StreamException('Error message', $quicError);
        
        $this->assertSame($quicError, $exception->getQuicError());
    }

    public function test_exception_inheritance(): void
    {
        $exception = new StreamException('Error', QuicError::STREAM_STATE_ERROR);
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }
} 