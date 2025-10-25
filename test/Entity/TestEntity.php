<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'test_entity')]
class TestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: 'date', nullable:true, name: 'date_by_ref')]
    public ?\DateTime $dateByRef = null;

    #[ORM\Column(type: 'date', nullable:true, name: 'date_by_value', options:['detectChangeByDatabaseValue' => true], )]
    public ?\DateTime $dateByValue = null;

}
