<?php

declare(strict_types=1);

namespace Tourze\QUIC\Streams\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QUIC\Core\Enum\QuicError;
use Tourze\QUIC\Streams\Exception\StreamException;

/**
 * StreamException 单元测试
 *
 * @internal
 */
#[CoversClass(StreamException::class)]
final class StreamExceptionTest extends AbstractExceptionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exception 测试不需要特殊设置
    }

    public function testConstructorWithQuicError(): void
    {
        $message = 'Test stream error';
        $quicError = QuicError::STREAM_STATE_ERROR;

        $exception = new StreamException($message, $quicError);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($quicError->value, $exception->getCode());
        $this->assertSame($quicError, $exception->getQuicError());
    }

    public function testConstructorWithPreviousException(): void
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

    public function testExtendsException(): void
    {
        $exception = new StreamException('Test', QuicError::STREAM_LIMIT_ERROR);

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testGetQuicErrorReturnsOriginalError(): void
    {
        $quicError = QuicError::STREAM_STATE_ERROR;
        $exception = new StreamException('Test message', $quicError);

        $retrievedError = $exception->getQuicError();

        $this->assertSame($quicError, $retrievedError);
        $this->assertSame($quicError->value, $retrievedError->value);
    }

    public function testDifferentQuicErrorTypes(): void
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
