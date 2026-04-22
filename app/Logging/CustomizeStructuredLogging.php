<?php

namespace App\Logging;

use Monolog\Logger;

class CustomizeStructuredLogging
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new StructuredJsonFormatter());
        }
    }
}
