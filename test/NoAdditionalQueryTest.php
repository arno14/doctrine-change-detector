<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;

class NoAdditionalQueryTest extends AbstractTestCase
{
    public function testNoAdditionalQueryOnUnchangedDataTest(): void
    {
        // Initial state
        $this->insert(['id' => 111, 'date_by_value' => '2000-01-01'])
            ->assertDBValue('2000-01-01', 111, 'date_by_value')
            ->resetCountQueries();

        // retrieve the entity
        $entity = $this->entityManager->find(TestEntity::class, 111);
        $this->assertCountQueries(1)
             ->resetCountQueries();
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals('2000-01-01', $entity->dateByValue->format('Y-m-d'));

        // modify the entity with same value
        $entity->dateByValue = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->assertCountQueries(0) //No additional queries executed
            ->assertDBValue('2000-01-01', $entity->id, 'date_by_value')
            ->resetCountQueries();
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
        $this->assertCountQueries(1)
            ->assertDBValue('2000-01-01', $entity->id, 'date_by_value')
            ->resetCountQueries();

        // Flush again without real  changes
        $entity->dateByValue = new \DateTime('2000-01-01');
        $this->entityManager->flush();
        $this->markTestIncomplete('Needs fix');
        // @phpstan-ignore-next-line
        $this->assertCountQueries(1);
    }
}
