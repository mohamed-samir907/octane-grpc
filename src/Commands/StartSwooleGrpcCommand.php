<?php

namespace Mosamirzz\OctaneGrpc\Commands;

use Illuminate\Support\Str;
use Laravel\Octane\Commands\Command;
use Laravel\Octane\Commands\Concerns;
use Symfony\Component\Process\Process;
use Mosamirzz\OctaneGrpc\ServerStateFile;
use Laravel\Octane\Swoole\SwooleExtension;
use Laravel\Octane\Swoole\ServerProcessInspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Console\Command\SignalableCommandInterface;

#[AsCommand(name: 'octane:swoole-grpc')]
class StartSwooleGrpcCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithEnvironmentVariables, Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:swoole-grpc
                    {--host= : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane Swoole gRPC server';

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(
        ServerProcessInspector $inspector,
        ServerStateFile $serverStateFile,
        SwooleExtension $extension
    ) {
        if (! $extension->isInstalled()) {
            $this->components->error('The Swoole extension is missing.');

            return 1;
        }

        $this->ensurePortIsAvailable();

        if ($inspector->serverIsRunning()) {
            $this->components->error('Server is already running.');

            return 1;
        }

        if (config('octane.swoole.ssl', false) === true && ! defined('SWOOLE_SSL')) {
            $this->components->error('You must configure Swoole with `--enable-openssl` to support ssl.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile, $extension);

        $this->forgetEnvironmentVariables();

        $server = tap(new Process([
            (new PhpExecutableFinder)->find(),
            ...config('octane.swoole-grpc.php_options', []),
            config('octane.swoole-grpc.command', 'swoole-grpc-server'),
            $serverStateFile->path(),
        ], realpath(__DIR__ . '/../../bin'), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAVEL_OCTANE' => 1,
        ]))->start();

        return $this->runServer($server, $inspector, 'swoole');
    }

    /**
     * Write the server start "message" to the console.
     *
     * @return void
     */
    protected function writeServerRunningMessage()
    {
        $this->components->info('Server running…');

        $this->output->writeln([
            '',
            '  Local: <fg=white;options=bold>'.'grpc://'.$this->getHost().':'.$this->getPort().' </>',
            '',
            '  <fg=yellow>Press Ctrl+C to stop the server</>',
            '',
        ]);
    }

    /**
     * Get the Octane gRPC server host IP to bind on.
     *
     * @return string
     */
    protected function getHost()
    {
        return $this->option('host') ?? config('octane.grpc.host') ?? $_ENV['OCTANE_GRPC_HOST'] ?? '127.0.0.1';
    }

    /**
     * Get the Octane gRPC server port.
     *
     * @return string
     */
    protected function getPort()
    {
        return $this->option('port') ?? config('octane.grpc.port') ?? $_ENV['OCTANE_GRPC_PORT'] ?? '50051';
    }

    /**
     * Write the Swoole server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile,
        SwooleExtension $extension
    ) {
        $serverStateFile->writeState([
            'appName' => config('app.name', 'Laravel'),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'workers' => $this->workerCount($extension),
            'maxRequests' => $this->option('max-requests'),
            // 'publicPath' => public_path(),
            'storagePath' => storage_path(),
            'defaultServerOptions' => $this->defaultServerOptions($extension),
            'octaneConfig' => config('octane'),
        ]);
    }

    /**
     * Get the default Swoole server options.
     *
     * @return array
     */
    protected function defaultServerOptions(SwooleExtension $extension)
    {
        return [
            'daemonize' => false,
            'log_file' => storage_path('logs/swoole_grpc.log'),
            'log_level' => app()->environment('local') ? SWOOLE_LOG_INFO : SWOOLE_LOG_ERROR,
            'max_request' => $this->option('max-requests'),
            'package_max_length' => 10 * 1024 * 1024,
            'socket_buffer_size' => 10 * 1024 * 1024,
            // 'reactor_num' => $this->workerCount($extension),
            'worker_num' => $this->workerCount($extension),
        ];
    }

    /**
     * Get the number of workers that should be started.
     *
     * @return int
     */
    protected function workerCount(SwooleExtension $extension)
    {
        return $this->option('workers') === 'auto'
            ? $extension->cpuCount()
            : $this->option('workers');
    }

    /**
     * Write the server process output ot the console.
     *
     * @param  \Symfony\Component\Process\Process  $server
     * @return void
     */
    protected function writeServerOutput($server)
    {
        [$output, $errorOutput] = $this->getServerOutput($server);

        Str::of($output)
            ->explode("\n")
            ->filter()
            ->each(
                fn($output) => is_array($stream = json_decode($output, true))
                    ? $this->handleStream($stream)
                    : $this->components->info($output)
            );

        Str::of($errorOutput)
            ->explode("\n")
            ->filter()
            ->groupBy(fn($output) => $output)
            ->each(function ($group) {
                is_array($stream = json_decode($output = $group->first(), true)) && isset($stream['type'])
                    ? $this->handleStream($stream)
                    : $this->raw($output);
            });
    }

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $this->callSilent('octane:stop', [
            '--server' => 'swoole-grpc',
        ]);
    }
}
