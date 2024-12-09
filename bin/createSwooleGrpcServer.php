<?php

use Laravel\Octane\Stream;
use OpenSwoole\GRPC\Server;

$config = $serverState["octaneConfig"]['grpc'];

try {
    $host = $config['host'] ?? '0.0.0.0';

    $sock = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? SWOOLE_SOCK_TCP
        : SWOOLE_SOCK_TCP6;

    $server = new Server(
        $host,
        $config['port'] ?? 50051,
        $config['mode'] ?? SWOOLE_BASE,
        ($config['ssl'] ?? false)
            ? $sock | SWOOLE_SSL
            : $sock,
    );
} catch (Throwable $e) {
    Stream::shutdown($e);

    exit(1);
}

// remove enable_coroutine option because it enabled by default in the gRPC server
$defaultConfig = $serverState['defaultServerOptions'];
unset($defaultConfig['enable_coroutine']);

$server->set(array_merge(
    $defaultConfig,
    $config['options'] ?? []
));

return $server;
