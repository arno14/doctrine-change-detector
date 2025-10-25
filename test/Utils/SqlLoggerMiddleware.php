<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests\Utils;

use ArrayObject;
use Doctrine\DBAL\Driver;

class SqlLoggerMiddleware implements \Doctrine\DBAL\Driver\Middleware
{
    /**
     * @param ArrayObject<int,string> $queries
     */
    public function __construct(
        /** @var ArrayObject<int,string> */
        private ArrayObject $queries
    ) {
    }

    /**
     * @param Driver $driver
     * @return Driver
     */
    public function wrap(Driver $driver): Driver
    {
        return new LoggingDriver($driver, $this->queries);
    }
}
