<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use Doctrine\ORM\Mapping as ORM;
use MLukman\DoctrineHelperBundle\Type\Hex32IdGenerator;

/**
 * A trait that an entity class can use to have its Id generated using 32-character hexadecimal,
 * e.g ab2d1ce90f341c76
 */
trait Hex32IdEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: Hex32IdGenerator::class)]
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
}
