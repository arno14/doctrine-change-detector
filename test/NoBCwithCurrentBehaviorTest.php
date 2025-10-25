<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;

class NoBCwithCurrentBehaviorTest extends AbstractTestCase
{
    public function testNoChangeOnMappedFieldWithoutOption(): void
    {
        // Initial state
        $this->resetCountQueries();

        // Create and persist entity
        $entity = new TestEntity();
        $entity->dateByRef = new \DateTime('2000-01-01');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->assertCountQueries(1);

        // Update with same value
        $entity->dateByRef = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(2);

        // Update with different value
        $entity->dateByRef = new \DateTime('2020-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(3);

        // Clear and reload entity
        $this->entityManager->clear();
        $entity = $this->entityManager->find(TestEntity::class, $entity->id);
        $this->assertCountQueries(4);

        // Update with same value after reload
        $entity->dateByRef = new \DateTime('2020-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(5);
    }
}
