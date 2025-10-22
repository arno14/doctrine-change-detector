<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests\Utils;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;

class LoggingDriver implements Driver
{
    private Driver $inner;
    private \ArrayObject $queries;

    public function __construct(Driver $inner, \ArrayObject $queries)
    {
        $this->inner = $inner;
        $this->queries = $queries;
    }

    public function connect(array $params): DriverConnection
    {
        $conn = $this->inner->connect($params);
        return new LoggingConnection($conn, $this->queries);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
    {
        return $this->inner->getDatabasePlatform($versionProvider);
    }

    public function getExceptionConverter(): \Doctrine\DBAL\Driver\API\ExceptionConverter
    {
        return $this->inner->getExceptionConverter();
    }
}
