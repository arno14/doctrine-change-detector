<?php


declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests;

use Arno14\DoctrineChangeDetector\Tests\Entity\TestEntity;

class ChangeAreComputedByDBValueTest extends AbstractTestCase
{
    public function testEntityRetrievedFromManagerHasValueComputedByDBValue(): void
    {
        // Initial state
        $this->insert(['id' => 111, 'date_by_value' => '2000-01-01'])
             ->resetCountQueries();

        $entity = $this->entityManager->find(Entity\TestEntity::class, 111);
        $this->assertInstanceOf(TestEntity::class, $entity);
        $this->assertEquals('2000-01-01', $entity->dateByValue->format('Y-m-d'));
        $this->assertCountQueries(1)
            ->resetCountQueries();

        //change the datetime object value
        $entity->dateByValue->modify('+1 day');
        $this->entityManager->flush();
        $this->assertCountQueries(1)
            ->assertDBValue('2000-01-02', $entity->id, 'date_by_value')
            ->resetCountQueries();

        //set back the datetime object to original date
        $entity->dateByValue->modify('-1 day');
        $this->entityManager->flush();
        $this->assertCountQueries(1)
            ->assertDBValue('2000-01-01', $entity->id, 'date_by_value')
            ->resetCountQueries();
    }

    public function testEntityNewlyCreatedHasValueComputedByDBValue(): void
    {
        $this->resetCountQueries();

        $entity = new TestEntity();
        $entity->dateByValue = new \DateTime('2000-01-01');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->assertCountQueries(1)
            ->assertDBValue('2000-01-01', $entity->id, 'date_by_value')
            ->resetCountQueries();

        //change the datetime object value
        $entity->dateByValue->modify('+1 day');
        $this->entityManager->flush();
        $this->assertCountQueries(1)
            ->assertDBValue('2000-01-02', $entity->id, 'date_by_value')
            ->resetCountQueries();
    }
}
