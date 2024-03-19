<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use Doctrine\ORM\Mapping as ORM;
use MLukman\DoctrineHelperBundle\Type\Hex8IdGenerator;

/**
 * A trait that an entity class can use to have its Id generated using 8-character hexadecimal,
 * e.g ab2d1ce9
 */
trait Hex8IdEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(length: 8)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: Hex8IdGenerator::class)]
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }
}