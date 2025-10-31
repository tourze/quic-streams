# QUIC 流管理包

[English](README.md) | 中文

[![Latest Version](https://img.shields.io/packagist/v/tourze/quic-streams.svg?style=flat-square)](https://packagist.org/packages/tourze/quic-streams)
[![License](https://img.shields.io/packagist/l/tourze/quic-streams.svg?style=flat-square)](https://github.com/tourze/quic-streams/blob/main/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/quic-streams/phpunit.yml?style=flat-square)](https://github.com/tourze/quic-streams/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/quic-streams.svg?style=flat-square)](https://codecov.io/gh/tourze/quic-streams)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/quic-streams.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/quic-streams)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/quic-streams.svg?style=flat-square)](https://packagist.org/packages/tourze/quic-streams)

QUIC协议流管理实现包，提供完整的QUIC流生命周期管理功能。

## 功能特性

- ✅ 双向流和单向流支持
- ✅ 流状态机管理
- ✅ 数据缓冲和排序
- ✅ 流量控制集成
- ✅ 异常错误处理
- ✅ 内存优化的缓冲区管理

## 安装

```bash
composer require tourze/quic-streams
```

## 系统要求

- PHP 8.1 或更高版本
- tourze/quic-core
- tourze/quic-flow-control
- tourze/quic-frames

本包遵循RFC 9000规范实现QUIC流管理，主要组件包括：

### 核心组件

1. **Stream（流基类）** - 抽象基类，定义流的基本行为
2. **BidirectionalStream（双向流）** - 支持双向数据传输
3. **UnidirectionalStream（单向流）** - 仅支持单向数据传输
4. **StreamManager（流管理器）** - 管理所有流的生命周期
5. **StreamBuffer（数据缓冲区）** - 处理数据的缓冲和重排序
6. **StreamStateMachine（状态机）** - 管理流状态转换
7. **StreamException（异常处理）** - 流相关的异常处理

### 流类型定义

根据RFC 9000第2.1节：

- `CLIENT_BIDI` (0) - 客户端发起的双向流（4n+0）
- `SERVER_BIDI` (1) - 服务器发起的双向流（4n+1）
- `CLIENT_UNI` (2) - 客户端发起的单向流（4n+2）
- `SERVER_UNI` (3) - 服务器发起的单向流（4n+3）

## 快速开始

### 基本用法

```php
use Tourze\QUIC\Streams\StreamManager;
use Tourze\QUIC\Core\Enum\StreamType;

// 创建流管理器（服务器端）
$manager = new StreamManager(isServer: true);

// 创建双向流
$stream = $manager->createStream(StreamType::SERVER_BIDI);

// 发送数据
$stream->send('Hello World!', fin: false);
$stream->send('Final message', fin: true);

// 接收数据
$stream->receive('Response data', offset: 0, fin: true);

// 获取流统计信息
$stats = $stream->getStats();
print_r($stats);
```

### 高级用法

```php
use Tourze\QUIC\Streams\StreamBuffer;
use Tourze\QUIC\Streams\StreamStateMachine;

// 自定义缓冲区
$buffer = new StreamBuffer(maxBufferSize: 2048);

// 状态机管理
$stateMachine = new StreamStateMachine();
$canSend = $stateMachine->canSend();
$isClosed = $stateMachine->isClosed();

// 流管理器高级功能
$allStreams = $manager->getAllStreams();
$clientStreams = $manager->getStreamsByType(StreamType::CLIENT_BIDI);
$removedCount = $manager->garbageCollect();
```

## 错误处理

```php
use Tourze\QUIC\Streams\StreamException;
use Tourze\QUIC\Core\Enum\QuicError;

try {
    $stream->send('data');
} catch (StreamException $e) {
    $error = $e->getQuicError();
    switch ($error) {
        case QuicError::STREAM_STATE_ERROR:
            echo "流状态错误";
            break;
        case QuicError::FLOW_CONTROL_ERROR:
            echo "流量控制错误";
            break;
        default:
            echo "其他错误: " . $error->name;
    }
}
```

## 性能优化

### 缓冲区管理

```php
// 检查缓冲区使用情况
if ($buffer->isNearFull()) {
    // 缓冲区接近满，考虑流量控制
}

// 获取缓冲区统计
$stats = $buffer->getStats();
echo "发送缓冲区大小: " . $stats['send_buffer_size'];
echo "接收缓冲区大小: " . $stats['recv_buffer_size'];
```

### 垃圾回收

```php
// 定期清理已完成的流
$removedCount = $manager->garbageCollect();
echo "清理了 {$removedCount} 个流";
```

## 测试

```bash
# 运行完整测试套件
./vendor/bin/phpunit packages/quic-streams/tests

# 运行特定测试
./vendor/bin/phpunit packages/quic-streams/tests/Unit/StreamBufferTest.php

# 生成代码覆盖率报告
./vendor/bin/phpunit --coverage-html coverage packages/quic-streams/tests
```

## 依赖关系

- `tourze/quic-core` - QUIC协议核心定义
- `tourze/quic-frames` - QUIC帧处理
- `tourze/quic-flow-control` - 流量控制管理

## 协议兼容性

本包完全遵循以下RFC规范：

- [RFC 9000](https://tools.ietf.org/html/rfc9000) - QUIC: A UDP-Based Multiplexed and Secure Transport
- 特别是第2节（流的概念）和第3节（流状态）

## 贡献指南

请查看 [CONTRIBUTING.md](../../CONTRIBUTING.md) 获取详细信息。

## 许可证

MIT 许可证。请查看 [LICENSE](LICENSE) 文件获取更多信息。
