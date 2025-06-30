<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Streams\Exception\StreamException;

/**
 * StreamException 单元测试
 */
final class StreamExceptionTest extends TestCase
{
    public function test_constructor_with_quic_error(): void
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

    public function test_extends_exception(): void
    {
        $exception = new StreamException('Test', QuicError::STREAM_LIMIT_ERROR);
        
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_get_quic_error_returns_original_error(): void
    {
        $quicError = QuicError::STREAM_STATE_ERROR;
        $exception = new StreamException('Test message', $quicError);
        
        $retrievedError = $exception->getQuicError();
        
        $this->assertSame($quicError, $retrievedError);
        $this->assertSame($quicError->value, $retrievedError->value);
    }

    public function test_different_quic_error_types(): void
    {
        $testCases = [
            QuicError::STREAM_STATE_ERROR,
            QuicError::FLOW_CONTROL_ERROR,
            QuicError::STREAM_LIMIT_ERROR,
        ];

        foreach ($testCases as $quicError) {
            $exception = new StreamException('Test message', $quicError);
            
            $this->assertSame($quicError, $exception->getQuicError());
            $this->assertSame($quicError->value, $exception->getCode());
        }
    }
} 