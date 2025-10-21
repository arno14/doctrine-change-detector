<?php

declare(strict_types=1);

use Arno14\DoctrineChangeDetector\ChangeDetectorListener;
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

// =========================
// Middleware & Logging Driver
// =========================
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

class LoggingDriver implements Driver
{
    private Driver $inner;
    private \ArrayObject $queries;

    public function __construct(Driver $inner, \ArrayObject $queries)
    {
        $this->inner = $inner;
        $this->queries = $queries;
    }

    public function connect(array $params):DriverConnection
    {
        $conn = $this->inner->connect($params);
        return new LoggingConnection($conn, $this->queries);
    }

    public function getDatabasePlatform(ServerVersionProvider $versionProvider):AbstractPlatform
    {
        return $this->inner->getDatabasePlatform($versionProvider);
    }

    public function getExceptionConverter(): \Doctrine\DBAL\Driver\API\ExceptionConverter
    {
        return $this->inner->getExceptionConverter();
    }
}

class LoggingConnection implements DriverConnection

{
    private DriverConnection $inner;
    private \ArrayObject $queries;

    public function __construct(DriverConnection $inner, \ArrayObject $queries)
    {
        $this->inner = $inner;
        $this->queries = $queries;
    }

    public function getServerVersion(): string
    {
        return $this->inner->getServerVersion();
    }

    public function getNativeConnection()
    {
        return $this->inner->getNativeConnection();
    }
    
    public function prepare(string $sql):DriverStatement
    {
        $this->queries->append($sql);
        return $this->inner->prepare($sql);
    }

    public function query(string $sql):DriverResult
    {
        $this->queries->append($sql);
        return $this->inner->query($sql);
    }

    public function exec(string $sql):int|string
    {
        $this->queries->append($sql);
        return $this->inner->exec($sql);
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->inner->lastInsertId($name);
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

// =========================
// PHPUnit Test
// =========================
class ChangeDetectorListenerTest extends TestCase
{
    private EntityManager $entityManager;
    private \ArrayObject $executedQueries;

    protected function setUp(): void
    {
        $this->executedQueries = new \ArrayObject();

        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__],
            isDevMode: true
        );

        $dbalConfig = new Configuration();
        $dbalConfig->setMiddlewares([
            new SqlLoggerMiddleware($this->executedQueries),
        ]);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $dbalConfig);


        $this->entityManager = new EntityManager($connection, $ormConfig);

        $this->entityManager->getEventManager()->addEventSubscriber(
            new ChangeDetectorListener()
        );

        $tool = new SchemaTool($this->entityManager);
        $classes = [$this->entityManager->getClassMetadata(TestEntity::class)];
        $tool->createSchema($classes);
    }

    public function testSecondFlushDoesNotTriggerQueries(): void
    {
        $this->executedQueries->exchangeArray([]); // Clear previous queries
        $entity = new TestEntity();
        $entity->birthDay = new \DateTime('2000-01-01');

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertCount(1, $this->executedQueries, 'First flush should trigger SQL queries.');

        $entity->birthDay = new \DateTime('2000-01-01');
        // No changes, flush again
        $this->entityManager->flush();

        $this->assertCount(2, 
            $this->executedQueries, 
            'Second flush should not trigger SQL queries.'.json_encode($this->executedQueries->getArrayCopy()));

        
        $this->entityManager->clear();

        $entity = $this->entityManager->find(TestEntity::class, $entity->id);

        $this->assertCount(3, 
            $this->executedQueries, 
            'Second flush should not trigger SQL queries.'.json_encode($this->executedQueries->getArrayCopy()));

        $entity->birthDay = new \DateTime('2000-01-01');

        $this->entityManager->flush();

          $this->assertCount(3, 
            $this->executedQueries, 
            'Thrird flush should not trigger SQL queries.'.json_encode($this->executedQueries->getArrayCopy()));

    }
}

// =========================
// Entity
// =========================
#[\Doctrine\ORM\Mapping\Entity]
class TestEntity
{
    #[\Doctrine\ORM\Mapping\Id]
    #[\Doctrine\ORM\Mapping\Column(type: 'integer')]
    #[\Doctrine\ORM\Mapping\GeneratedValue]
    public ?int $id = null;

    #[\Doctrine\ORM\Mapping\Column(type: 'date', options:['detectChangeByDatabaseValue'=>true])]
    public ?\DateTime $birthDay = null;
}
