<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;

#[ORM\HasLifecycleCallbacks]
trait UuidEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
}