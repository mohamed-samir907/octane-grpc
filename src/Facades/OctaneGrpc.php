<?php

namespace Mosamirzz\OctaneGrpc\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mosamirzz\OctaneGrpc\OctaneGrpc
 */
class OctaneGrpc extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'octane-grpc';
    }
}
