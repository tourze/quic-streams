# QUIC Streams Package

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

## 基本使用

### 创建流管理器

```php
use Tourze\QUIC\Streams\StreamManager;
use Tourze\QUIC\FlowControl\FlowControlManager;

// 创建流控制管理器（可选）
$flowControlManager = new FlowControlManager();

// 创建流管理器
$streamManager = new StreamManager(
    isServer: true, 
    flowControlManager: $flowControlManager
);
```

### 创建流

```php
use Tourze\QUIC\Core\Enum\StreamType;

// 创建双向流
$bidirectionalStream = $streamManager->createStream(StreamType::CLIENT_BIDI);

// 创建单向流
$unidirectionalStream = $streamManager->createStream(StreamType::CLIENT_UNI);
```

### 发送和接收数据

```php
// 发送数据
$stream->send('Hello QUIC!', fin: false);
$stream->send('Final message', fin: true);

// 接收数据
$stream->receive('Received data', offset: 0, fin: false);
```

### 流状态管理

```php
use Tourze\QUIC\Streams\StreamStateMachine;

$stateMachine = new StreamStateMachine();

// 状态转换
$success = $stateMachine->transitionSend(StreamSendState::SEND);

// 检查状态
if ($stateMachine->canSend()) {
    // 可以发送数据
}

if ($stateMachine->isClosed()) {
    // 流已关闭
}
```

### 缓冲区管理

```php
use Tourze\QUIC\Streams\StreamBuffer;

// 创建缓冲区（默认1MB）
$buffer = new StreamBuffer();

// 添加发送数据
$offset = $buffer->addSendData('Data to send');

// 获取发送数据
$sendData = $buffer->getSendData(maxLength: 1200);

// 添加接收数据
$buffer->addRecvData('Received data', offset: 0);

// 获取连续的接收数据
$recvData = $buffer->getRecvData();
```

## 类结构

### 核心类

- `Stream` - 抽象流基类
- `BidirectionalStream` - 双向流实现
- `UnidirectionalStream` - 单向流实现
- `StreamManager` - 流管理器
- `StreamBuffer` - 数据缓冲区
- `StreamStateMachine` - 流状态机
- `StreamException` - 流异常

### 流类型

根据RFC 9000规范：

- `StreamType::CLIENT_BIDI` (0) - 客户端发起的双向流
- `StreamType::SERVER_BIDI` (1) - 服务器发起的双向流  
- `StreamType::CLIENT_UNI` (2) - 客户端发起的单向流
- `StreamType::SERVER_UNI` (3) - 服务器发起的单向流

## 异常处理

```php
use Tourze\QUIC\Streams\StreamException;
use Tourze\QUIC\Core\Enum\QuicError;

try {
    $stream->send('data');
} catch (StreamException $e) {
    $quicError = $e->getQuicError();
    echo "QUIC Error: " . $quicError->name;
}
```

## 测试

```bash
# 运行单元测试
./vendor/bin/phpunit packages/quic-streams/tests

# 运行静态分析
./vendor/bin/phpstan analyse packages/quic-streams/src --level=max
```

## 依赖关系

- `tourze/quic-core` - QUIC核心定义
- `tourze/quic-frames` - QUIC帧处理
- `tourze/quic-flow-control` - 流量控制

## 许可证

MIT License
