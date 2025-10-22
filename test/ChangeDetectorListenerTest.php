<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;

class ChangeDetectorListenerTest extends AbstractTestCase
{
    public function testSecondFlushDoesNotTriggerQueries(): void
    {
        // Initial state
        $this->resetCountQueries();

        // Create and persist entity
        $entity = new TestEntity();
        $entity->birthDay = new \DateTime('2000-01-01');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->assertCountQueries(1);

        // Update with same value
        $this->resetCountQueries();
        $entity->birthDay = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(1);
        //@todo change this, we expect zero queries as the value is the same

        // Clear and reload entity
        $this->resetCountQueries();
        $this->entityManager->clear();
        $entity = $this->entityManager->find(TestEntity::class, $entity->id);
        $this->assertCountQueries(1);

        // Update with same value after reload
        $this->resetCountQueries();
        $entity->birthDay = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(0);
    }
}
