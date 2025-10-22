<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests\Utils;

use Doctrine\DBAL\Driver;

class SqlLoggerMiddleware implements \Doctrine\DBAL\Driver\Middleware
{
    private \ArrayObject $queries;

    public function __construct(\ArrayObject $queries)
    {
        $this->queries = $queries;
    }

    public function wrap(Driver $driver): Driver
    {
        return new LoggingDriver($driver, $this->queries);
    }
}
