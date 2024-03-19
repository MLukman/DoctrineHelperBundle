<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use Doctrine\ORM\Mapping as ORM;
use MLukman\DoctrineHelperBundle\Type\Hex16IdGenerator;

/**
 * A trait that an entity class can use to have its Id generated using 16-character hexadecimal,
 * e.g ab2d1ce90f341c76
 */
trait Hex16IdEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(length: 16)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: Hex16IdGenerator::class)]
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
}