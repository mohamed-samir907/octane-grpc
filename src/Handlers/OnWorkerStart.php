<?php

namespace Mosamirzz\OctaneGrpc\Handlers;

use Throwable;
use Laravel\Octane\Stream;
use OpenSwoole\GRPC\Server;
use Laravel\Octane\DispatchesEvents;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Swoole\SwooleClient;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Swoole\SwooleExtension;

class OnWorkerStart
{
    use DispatchesEvents;

    public function __construct(
        protected SwooleExtension $extension,
        protected $basePath,
        protected array $serverState,
        protected bool $shouldSetProcessName = true
    ) {}

    /**
     * @return \Illuminate\Foundation\Application
     */
    public function __invoke(Server $server, array $initialInstances = [])
    {
        $this->clearOpcodeCache();

        try {
            // First we will create an instance of the Laravel application that can serve as
            // the base container instance we will clone from on every request. This will
            // also perform the initial bootstrapping that's required by the framework.
            $app = (new ApplicationFactory($this->basePath))->createApplication(
                array_merge(
                    $initialInstances,
                    [\Laravel\Octane\Contracts\Client::class => new SwooleClient],
                )
            );

            $this->dispatchEvent($app, new WorkerStarting($app));
        } catch (Throwable $e) {
            Stream::shutdown($e);
        }

        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->serverState['appName'], 'worker process');
        }

        return $app;
    }

    /**
     * Clear the APCu and Opcache caches.
     *
     * @return void
     */
    protected function clearOpcodeCache()
    {
        foreach (['apcu_clear_cache', 'opcache_reset'] as $function) {
            if (function_exists($function)) {
                $function();
            }
        }
    }
}
