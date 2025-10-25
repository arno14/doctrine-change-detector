<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;

class NoAdditionalQueryTest extends AbstractTestCase
{
    public function testNoAdditionalQueryOnUnchangedDataTest(): void
    {
        // Initial state
        $this->entityManager->getConnection()
            ->insert('test_entity', ['id' => 111, 'dateByValue' => '2000-01-01']);
        $this->resetCountQueries();


        $entity = $this->entityManager->find(TestEntity::class, 111);
        $this->assertCountQueries(1);
        $this->assertEquals('2000-01-01', $entity->dateByValue->format('Y-m-d'));

        $entity->dateByValue = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(1);
    }

    public function testNoAdditionalQueryOnNewlyPersistedEntityTest(): void
    {
        // Initial state
        $this->resetCountQueries();

        // Create and persist entity
        $entity = new TestEntity();
        $entity->dateByValue = new \DateTime('2000-01-01');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->assertCountQueries(1);

        // Flush again without changes
        $entity->dateByValue = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->markTestIncomplete('Needs fix');
        $this->assertCountQueries(1);
    }
}
