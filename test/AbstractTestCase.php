<?php

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\ChangeDetectorListener;
use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;
use Arno14\DoctrineChangeDetector\Tests\Utils\SqlLoggerMiddleware;
use ArrayObject;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

abstract class AbstractTestCase extends \PHPUnit\Framework\TestCase
{
    protected EntityManager $entityManager;

    /**
     * @var ArrayObject<int,string>
     */
    protected ArrayObject $executedQueries;

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

    public function assertCountQueries(int $expectedCount): static
    {
        $this->assertCount(
            $expectedCount,
            $this->executedQueries,
            json_encode($this->executedQueries->getArrayCopy(), JSON_PRETTY_PRINT)
        );

        return $this;
    }

    public function resetCountQueries(): static
    {
        $this->executedQueries->exchangeArray([]);

        return $this;
    }

    public function assertDBValue(mixed $expectedValue, int $id, string $columnName = 'date_by_value', string $tableName = 'test_entity'): static
    {
        $row = $this->entityManager->getConnection()
            ->fetchAssociative("SELECT {$columnName} FROM {$tableName} WHERE id = ?", [$id]);

        $this->assertIsArray($row);
        $this->assertArrayHasKey($columnName, $row);

        $this->assertEquals($expectedValue, $row[$columnName]);

        return $this;
    }

    /**
     * @param array<string,mixed> $datas
     */
    public function insert(array $datas, string $tableName = 'test_entity'): static
    {
        $this->entityManager->getConnection()
            ->insert($tableName, $datas);

        return $this;
    }
}
