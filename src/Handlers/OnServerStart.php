<?php

namespace Mosamirzz\OctaneGrpc\Handlers;

use Laravel\Octane\Swoole\Actions\EnsureRequestsDontExceedMaxExecutionTime;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SwooleExtension;
use OpenSwoole\GRPC\Server;
use ReflectionClass;
use Swoole\Timer;

class OnServerStart
{
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected SwooleExtension $extension,
        protected string $appName,
        protected int $maxExecutionTime,
        protected bool $shouldSetProcessName = true
    ) {}

    /**
     * Handle the "start" Swoole event.
     *
     * @return void
     */
    public function __invoke(Server $server)
    {
        $ref = new ReflectionClass($server);
        /**
         * @var \Swoole\Http\Server $server
         */
        $server = $ref->getProperty('server')->getValue($server);

        $this->serverStateFile->writeProcessIds(
            $server->master_pid,
            $server->manager_pid
        );

        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->appName, 'master process');
        }
    }
}
