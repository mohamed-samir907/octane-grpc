<?php

namespace Mosamirzz\OctaneGrpc\Handlers;

use Laravel\Octane\DispatchesEvents;
use OpenSwoole\GRPC\MessageInterface;
use Laravel\Octane\CurrentApplication;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Middleware\MiddlewareInterface;

class RequestSandboxMiddleware implements MiddlewareInterface
{
    use DispatchesEvents;

    public function process(MessageInterface $request, RequestHandlerInterface $handler): MessageInterface
    {
        /** @var \OpenSwoole\GRPC\Context $context */
        $context = $request->getContext();

        /** @var \Illuminate\Foundation\Application $sandbox */
        $app = $context->getValue("WORKER_CONTEXT")->getValue("app");

        // We will clone the application instance so that we have a clean copy to switch
        // back to once the request has been handled. This allows us to easily delete
        // certain instances that got resolved / mutated during a previous request.
        CurrentApplication::set($sandbox = clone $app);

        // Process the gRPC request
        $response = $handler->handle($request);

        $sandbox->flush();

        // After the request handling process has completed we will unset some variables
        // plus reset the current application state back to its original state before
        // it was cloned. Then we will be ready for the next worker iteration loop.
        unset($sandbox, $context, $request, $handler);

        CurrentApplication::set($app);

        return $response;
    }
}
