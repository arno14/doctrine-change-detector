<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests\Utils;

use Arno14\DoctrineChangeDetector\ChangeDetectorListener;
use ArrayObject;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\ServerVersionProvider;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use PSpell\Config;

class LoggingConnection implements DriverConnection
{
    /**
     * @param ArrayObject<int,string> $queries
     */
    public function __construct(
        private DriverConnection $inner,
        private ArrayObject $queries
    ) {
    }

    public function getServerVersion(): string
    {
        return $this->inner->getServerVersion();
    }

    public function getNativeConnection()
    {
        return $this->inner->getNativeConnection();
    }

    public function prepare(string $sql): DriverStatement
    {
        $this->queries->append($sql);
        return $this->inner->prepare($sql);
    }

    public function query(string $sql): DriverResult
    {
        $this->queries->append($sql);
        return $this->inner->query($sql);
    }

    public function exec(string $sql): int|string
    {
        $this->queries->append($sql);
        return $this->inner->exec($sql);
    }

    public function lastInsertId(): string
    {
        return $this->inner->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->inner->beginTransaction();
    }

    public function commit(): void
    {
        $this->inner->commit();
    }

    public function rollBack(): void
    {
        $this->inner->rollBack();
    }

    public function quote(string $value): string
    {
        return $this->inner->quote($value);
    }
}
