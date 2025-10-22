<?php

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\ChangeDetectorListener;
use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;
use Arno14\DoctrineChangeDetector\Tests\Utils\SqlLoggerMiddleware;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

abstract class AbstractTestCase extends \PHPUnit\Framework\TestCase
{
    protected EntityManager $entityManager;
    protected \ArrayObject $executedQueries;

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

    public function assertCountQueries(int $expectedCount): void
    {
        $this->assertCount(
            $expectedCount,
            $this->executedQueries,
            json_encode($this->executedQueries->getArrayCopy(), JSON_PRETTY_PRINT)
        );
    }

    public function resetCountQueries(): self
    {
        $this->executedQueries->exchangeArray([]);

        return $this;
    }
}
