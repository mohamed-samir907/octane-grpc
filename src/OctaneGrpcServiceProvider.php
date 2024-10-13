<?php

namespace Mosamirzz\OctaneGrpc;

use Laravel\Octane\Exec;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Swoole\SignalDispatcher;
use Laravel\Octane\Swoole\ServerProcessInspector;

class OctaneGrpcServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\StartSwooleGrpcCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Register the main class to use with the facade
        $this->app->singleton('octane-grpc', OctaneGrpc::class);

        $this->app->bind(ServerStateFile::class, function ($app) {
            return new ServerStateFile($app['config']->get(
                'octane.grpc.state_file',
                storage_path('logs/octane-grpc-server-state.json')
            ));
        });

        $this->app->bind(ServerProcessInspector::class, function ($app) {
            return new ServerProcessInspector(
                $app->make(SignalDispatcher::class),
                $app->make(ServerStateFile::class),
                $app->make(Exec::class),
            );
        });
    }
}
