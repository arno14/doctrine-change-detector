<?php

declare(strict_types=1);

namespace Arno14\DoctrineChangeDetector\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: 'date', options:['detectChangeByDatabaseValue' => true])]
    public ?\DateTime $birthDay = null;
}
