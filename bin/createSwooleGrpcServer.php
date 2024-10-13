<?php

use Laravel\Octane\Stream;
use OpenSwoole\GRPC\Server;

$config = $serverState["octaneConfig"]['grpc'];

try {
    $host = $config['host'] ?? '127.0.0.1';

    $sock = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ? OpenSwoole\Constant::SOCK_TCP
        : OpenSwoole\Constant::SOCK_TCP6;

    $server = new Server(
        $host,
        $config['port'] ?? 50051,
        $config['mode'] ?? OpenSwoole\Http\Server::SIMPLE_MODE,
        ($config['ssl'] ?? false)
            ? $sock | OpenSwoole\Constant::SSL
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
