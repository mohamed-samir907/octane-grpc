<?php

namespace Mosamirzz\OctaneGrpc;

class OctaneGrpc
{
    /**
     * Write an error message to STDERR or to the SAPI logger if not in CLI mode.
     */
    public static function writeError(string $message): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, $message.PHP_EOL);

            return;
        }

        error_log($message, 4);
    }
}
